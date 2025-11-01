<?php
// src/success.php  (included by submit.php)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SEA <?=htmlspecialchars($SEA['id'])?> Submitted</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-success">SEA <?=htmlspecialchars($SEA['id'])?> Saved</h2>
    <div class="card">
        <div class="card-body">
            <p><strong>Requester:</strong> <?=htmlspecialchars($SEA['requester'])?></p>
            <p><strong>Description:</strong> <?=nl2br(htmlspecialchars($SEA['description']))?></p>
            <p><strong>Priority:</strong> <?=$SEA['priority']?> |
               <strong>Target:</strong> <?=$SEA['target_date']?></p>
            <?php if (!empty($SEA['attachments'])): ?>
                <p><strong>Attachments:</strong> <?=implode(', ', array_map('basename', $SEA['attachments']))?></p>
            <?php endif; ?>
        </div>
    </div>
    <a href="../public/index.html" class="btn btn-primary mt-3">New SEA</a>
    <a href="../src/view.php" class="btn btn-secondary mt-3">View All</a>
</div>
</body>
</html>