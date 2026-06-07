<?php 

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

// Perimiter check
requireLogin();

/**
 * Fetch a random hardware asset and apply dynamic market volatility to its price.
 * This simulates the "game engine" where players buy/sell assets at fluctuating prices
 */
function getRandomAsset($pdo, $excludeId = null) {
    if ($excludeId) {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id != ? ORDER BY RAND() LIMIT 1");
        $stmt->execute([$excludeId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY RAND() LIMIT 1");
    }

    $item = $stmt->fetch();

    if ($item) {
        // Simulate market volatility: +/- 20% price fluctuation
        // TODO: In a real game, you might want to base this on time or player actions rather than pure randomness or set it to a fixed value for testing admin purposes
        
        // Generate a random volatility percentage between -20% and +20% (e.g., -0.20 to +0.20 as a decimal)
        $volatility = rand(-20, 20) / 100;

        // Multiplier
        $multiplier = 1 + $volatility;

        // Calculate the current price with volatility applied rounded to 2 decimal places
        $item['current_price'] = round($item['price'] * $multiplier, 2);

        // Store the volatility percentage for display purposes (e.g., "+15%" or "-10%")
        $item['volatility'] = ($volatility >= 0 ? "+" : "") . round($volatility * 100) . "%";
    }
    return $item;
}

/**
 * Initialize the game state for a player by fetching a random asset and storing it in the session.
 * This should be called when the player first accesses the game page or after each round.
 */
function initGame($pdo) {
    $_SESSION['score'] = 0; // Reset score at the start of the game
    $_SESSION['lives'] = 3; // Set initial lives
    $_SESSION['current_asset'] = getRandomAsset($pdo); // Fetch the first random
    $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['current_asset']['id']); // Preload the next asset for smoother gameplay
    $_SESSION['round'] = 1; // Track the current round number
    $_SESSION['game_over'] = false; // Flag to track if the game has ended
}

/**
 * Process the player's guess and update the game state accordingly.
 * @param string $guess The player's guess ("higher" or "lower")
 * @return array An array containing the result of the guess and updated game state
 */
function processGuess($pdo, $guess) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game over. Please start a new game."];
    }

    $currentPrice = $_SESSION['current_asset']['current_price'];
    $nextPrice = $_SESSION['next_asset']['current_price'];
    $result = null;

    $isHigher = $nextPrice >= $currentPrice;
    $isLower = $nextPrice <= $currentPrice;

    // Determine if the player's guess is correct
    if (($guess === "higher" && $isHigher) || ($guess === "lower" && $isLower)) {
        $_SESSION['score'] += 1; // Increment score
        $result = "correct";
    } else {
        $_SESSION['lives'] -= 1; // Decrement lives
        $result = "incorrect";
    }

    // Check for game over condition
    if ($_SESSION['lives'] <= 0) {
        $_SESSION['game_over'] = true;
        // Save the player's score to the database
        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak) VALUES (?, ?)");
        $stmt->execute([$_SESSION['acct_id'], $_SESSION['score']]);
    } else {
        // Move to the next round: current asset becomes next asset, and fetch a new next
        $_SESSION['current_asset'] = $_SESSION['next_asset'];
        // Fetch a new next asset, ensuring it's different from the current one to avoid repetition
        $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['current_asset']['id']);

        $_SESSION['round'] += 1; // Increment round number
    }
}

/**
 * Saves the player's score to the database when the game ends. This is called from processGuess() when lives reach 0.
 */
function saveScore($pdo) {
    if (!empty($_SESSION['game_over']) && isset($_SESSION['acct_id']) && isset($_SESSION['score'])) {
        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak) VALUES (?, ?)");
        $stmt->execute([$_SESSION['acct_id'], $_SESSION['score']]);
    }
}