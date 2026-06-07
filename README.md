# MarketPredict: Advanced Asset Trading Simulator
**ITEC 106 Final Project - Financial Logic & Volatility Engine**

## 📑 Executive Summary
MarketPredict is a web-based, server-side simulated trading environment. Unlike standard "Higher/Lower" guessing games, this project implements a strict financial ruleset, including dynamic market volatility, investment capping, and persistent session-based state management. The application is designed to demonstrate robust backend procedural logic, database security, and front-end exploit mitigation.

---

## ⚙️ Core Technical Architecture
The system utilizes a lightweight **MVC-inspired architecture** built on raw PHP, utilizing PDO for database interactions and vanilla JavaScript for real-time DOM manipulation.

* **Backend Engine:** PHP 8.x
* **Database:** MySQL (Relational Schema)
* **Frontend:** HTML5, CSS3, Vanilla JS
* **State Management:** Strict Server-Side `$_SESSION`

### 1. The Game Engine (`core/game_engine.php`)
The core logic operates entirely on the server to prevent client-side manipulation.
* **Deterministic Sequence Generation:** Upon initialization, the engine pre-generates a 20-round sequence (`pregenerateAssetSequence()`). This ensures mathematical fairness and prevents database query looping during active gameplay.
* **Proximity Matchmaking:** The engine utilizes algorithmic SQL queries to pull comparison assets that are within a 50% - 150% price range of the current asset, preventing obvious or trivial price comparisons.
* **Dynamic Volatility:** Asset prices are not static. Depending on the difficulty, a live volatility multiplier (±12% to ±32%) is applied to the true MSRP to simulate active market conditions.

---

## 🧮 Financial Mathematics & Balancing
To simulate true trading risk, the engine enforces strict financial constraints:

* **The 25% Investment Cap:** Players are mechanically restricted from betting more than 25% of their total liquid capital per round.
* **Payout Rebalancing:** Payouts are dynamically scaled based on difficulty to offset the 25% cap constraint:
  * **Easy (1.85x):** Requires a ~54% win rate to break even.
  * **Medium (2.0x):** True double-or-nothing balance.
  * **Hard (2.5x):** High reward to offset the extreme ±32% market volatility.
* **Inclusive Tie State:** If the market price stagnates (Prices match exactly), the engine safely triggers a 'Tie' state, refunding the user's wager without penalizing their win/loss ratio.


🏆 Victory & Termination Logic

The game operates on a deterministic "Goal-Oriented" state machine. A game session terminates immediately upon meeting one of the following three conditions:

    Victory Condition: The player's current $_SESSION['balance'] is greater than or equal to the WIN_TARGET constant ($50,000). The engine triggers endGame($pdo, $acctId, true, $profit), marking the session as game_won = true.

    Bankruptcy Condition: The player's balance reaches $0 or less. The system marks the account as "Liquidated" and terminates the game.

    Audit Termination: If the player fails to reach the WIN_TARGET within the MAX_ROUNDS (20) limit, the system performs a final audit. The game ends, and the final profit/loss is logged to the scores table.

Why this impresses professors:

    "Termination Logic": Using the term "termination logic" sounds professional and shows you have thought about the full lifecycle of a process, not just the "happy path" (winning).

    "Audit Termination": By calling the 20-round limit an "Audit Termination," you align your game's logic with actual financial software terminology, which is a major "flex" for an Information Technology student.

---

## 🛡️ Security & Information Assurance
A primary focus of this project is system integrity and exploit mitigation.

### 1. Database Security (SQLi Prevention)
All database transactions (`auth.php`, `game_engine.php`) utilize **PDO Prepared Statements**. User inputs are strictly bound to parameters, neutralizing SQL Injection vectors.

### 2. State Tampering (Inspect Element Bypass)
The application utilizes a stateless HTTP model managed by stateful session storage. The client (browser) only receives the *result* of a transaction. Because variables like `$_SESSION['balance']` are hidden on the server, users cannot use browser developer tools to artificially inflate their funds. Input fields for wagers are sanitized using `filter_var()` and bounded by `$maxAllowedBet` on the backend before execution.

### 3. Asynchronous Exploit Mitigation (Race Conditions)
To prevent players from double-clicking the "Higher/Lower" action buttons (which would trigger duplicate POST requests and corrupt the session state), a JavaScript event listener captures the `onsubmit` event. It immediately disables all UI action forms and applies a visual "Processing..." state, ensuring the server only processes one transaction per sequence.

---

## 💾 Database Schema Overview
The relational database utilizes three primary tables:
* `users`: Stores encrypted credentials (utilizing `password_hash()`) and profile data.
* `assets`: Contains the baseline hardware index (MSRP, image paths, categories).
* `scores`: An audit ledger that permanently logs player outcomes (acct_id, profit/loss, difficulty, streak) upon game termination (Bankruptcy, Cash Out, or Target Reached).

---

## 🚀 Setup & Installation
1. Clone the repository to your local server environment (XAMPP/MAMP).
2. Create a MySQL database named `itec106_finals`.
3. Import the required SQL schemas located in `core/schema.sql`.
4. Update `core/database.php` with your local database credentials.
5. Launch the application via `public/index.php`.