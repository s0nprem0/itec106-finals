<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireLogin();

try {
    $query = "
        SELECT a.username, ms.max_streak as highest_streak, s.played_at as achieved_at, s.difficulty
        FROM (
            SELECT s.acct_id, MAX(s.streak) as max_streak
            FROM scores s
            GROUP BY s.acct_id
        ) ms
        JOIN accounts a ON ms.acct_id = a.acct_id
        JOIN scores s ON s.acct_id = ms.acct_id AND s.streak = ms.max_streak
        GROUP BY a.acct_id, a.username
        ORDER BY highest_streak DESC, achieved_at ASC
        LIMIT 10
    ";

    $stmt = $pdo->query($query);
    $top_scores = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Leaderboard Query Error: " . $e->getMessage());
    $top_scores = [];
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="lb-page">
    <div class="card lb-card">

        <div class="lb-header">
            <h1 class="lb-title">Global Standings</h1>
            <p class="lb-subtitle">Top intelligence operatives ranked by highest survival streak.</p>
        </div>

        <?php if (empty($top_scores)): ?>
            <div class="lb-empty">No performance data logged in the mainframe yet.</div>
        <?php else: ?>
            <div class="lb-table-wrapper">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th class="lb-th">Rank</th>
                        <th class="lb-th">Operative</th>
                        <th class="lb-th">Max Streak</th>
                        <th class="lb-th">Difficulty</th>
                        <th class="lb-th">Date Achieved</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; ?>
                    <?php foreach ($top_scores as $score): ?>
                        <?php
                        $rankClass = '';
                        if ($rank === 1)      $rankClass = 'lb-rank-1';
                        elseif ($rank === 2)  $rankClass = 'lb-rank-2';
                        elseif ($rank === 3)  $rankClass = 'lb-rank-3';

                        $diffClass = match($score['difficulty'] ?? 'medium') {
                            'easy'   => 'lb-diff-easy',
                            'hard'   => 'lb-diff-hard',
                            default  => 'lb-diff-medium',
                        };
                        ?>
                        <tr class="lb-tr">
                            <td class="lb-td lb-rank <?= $rankClass ?>">#<?= $rank ?></td>
                            <td class="lb-td lb-username">
                                <?= htmlspecialchars($score['username']) ?>
                            </td>
                            <td class="lb-td lb-streak"><?= $score['highest_streak'] ?></td>
                            <td class="lb-td"><span class="lb-diff-badge <?= $diffClass ?>"><?= htmlspecialchars(ucfirst($score['difficulty'] ?? 'medium')) ?></span></td>
                            <td class="lb-td lb-date"><?= date('M j, Y', strtotime($score['achieved_at'])) ?></td>
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
