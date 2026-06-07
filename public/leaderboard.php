<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireLogin();

try {
    $query = "
        SELECT a.username, COALESCE(SUM(s.profit), 0) as total_profit,
               MAX(s.played_at) as last_played, COUNT(s.id) as games_played
        FROM accounts a
        JOIN scores s ON a.acct_id = s.acct_id
        WHERE s.profit IS NOT NULL
        GROUP BY a.acct_id, a.username
        ORDER BY total_profit DESC
        LIMIT 10
    ";

    $stmt = $pdo->query($query);
    $top_profits = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Leaderboard Query Error: " . $e->getMessage());
    $top_profits = [];
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="lb-page">
    <div class="card lb-card">

        <div class="lb-header">
            <h1 class="lb-title">Global Standings</h1>
            <p class="lb-subtitle">Top operatives ranked by total virtual profit.</p>
        </div>

        <?php if (empty($top_profits)): ?>
            <div class="lb-empty">No profit data logged yet. Play some games first!</div>
        <?php else: ?>
            <div class="lb-table-wrapper">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th class="lb-th">Rank</th>
                        <th class="lb-th">Operative</th>
                        <th class="lb-th">Total Profit</th>
                        <th class="lb-th">Games</th>
                        <th class="lb-th">Last Played</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; ?>
                    <?php foreach ($top_profits as $score): ?>
                        <?php
                        $rankClass = '';
                        if ($rank === 1)      $rankClass = 'lb-rank-1';
                        elseif ($rank === 2)  $rankClass = 'lb-rank-2';
                        elseif ($rank === 3)  $rankClass = 'lb-rank-3';

                        $is_positive = $score['total_profit'] >= 0;
                        ?>
                        <tr class="lb-tr">
                            <td class="lb-td lb-rank <?= $rankClass ?>">#<?= $rank ?></td>
                            <td class="lb-td lb-username">
                                <?= htmlspecialchars($score['username']) ?>
                            </td>
                            <td class="lb-td lb-profit <?= $is_positive ? 'lb-profit-positive' : 'lb-profit-negative' ?>">
                                <?= $is_positive ? '+' : '-' ?>$<?= number_format(abs($score['total_profit']), 2) ?>
                            </td>
                            <td class="lb-td"><?= $score['games_played'] ?></td>
                            <td class="lb-td lb-date"><?= date('M j, Y', strtotime($score['last_played'])) ?></td>
                        </tr>
                        <?php $rank++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
