<?php
// src/view.php
require_once __DIR__ . '/../../config.php';

$files = glob(DATA_DIR . '/sea-*.json');
$seas = array_map(fn($f) => json_decode(file_get_contents($f), true), $files);
usort($seas, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All SEAs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/style.css">
</head>

<body>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>Simulator Engineering Authorizations</h1>
      <a href="../public/index.html" class="btn btn-primary">+ New SEA</a>
    </div>

    <?php if (empty($seas)): ?>
      <div class="alert alert-info text-center">
        No SEAs created yet. <a href="../public/index.html">Create one</a>.
      </div>
    <?php else: ?>
      <div class="row">
        <?php foreach ($seas as $s): ?>
          <div class="col-lg-6 col-xxl-4 mb-4">
            <div class="card h-100 shadow-sm">
              <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= h($s['id']) ?></strong>
                <span class="badge bg-<?=
                                      ($s['status'] ?? 'Planning') === 'Completed' ? 'success' : (($s['status'] ?? 'Planning') === 'In Work' ? 'primary' : 'warning')  // UPDATED: Badge logic
                                      ?>">
                  <?= h($s['status'] ?? 'Planning') ?> // UPDATED: Default
                </span>
              </div>
              <div class="card-body">
                <p class="mb-1"><strong>Requester:</strong> <?= h($s['requester']) ?></p>
                <p class="mb-1"><strong>Description:</strong>
                  <?= h(substr($s['description'], 0, 100)) ?><?= strlen($s['description']) > 100 ? '...' : '' ?>
                </p>
                <p class="mb-2"><small class="text-muted">
                    <?= h($s['timestamp']) ?> | v<?= $s['version'] ?? 1 ?>
                  </small></p>

                <!-- ACTION BUTTONS -->
                <div class="btn-group w-100" role="group">
                  <!-- EDIT -->
                  <a href="../public/index.html?id=<?= urlencode($s['id']) ?>"
                    class="btn btn-sm btn-outline-warning" title="Edit">
                    Edit
                  </a>

                  <!-- PRINT PDF -->
                  <a href="../src/print_sea.php?id=<?= urlencode($s['id']) ?>"
                    class="btn btn-sm btn-success" target="_blank" title="Print PDF">
                    Print PDF
                  </a>

                  <!-- DOWNLOAD JSON -->
                  <a href="../data/<?= basename($s['_filename'] ?? '') ?>"
                    class="btn btn-sm btn-outline-primary" download title="Download JSON">
                    JSON
                  </a>

                  <!-- DELETE (optional) -->
                  <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="deleteSEA('<?= addslashes($s['id']) ?>', '<?= basename($s['_filename'] ?? '') ?>')"
                    title="Delete">
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Delete Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Delete SEA <strong><span id="deleteId"></span></strong>?</p>
          <p class="text-muted small">This removes all files and history.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <form id="deleteForm" method="POST" action="delete_sea.php" style="display:inline;">
            <input type="hidden" name="filename" id="deleteFilename">
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function deleteSEA(id, filename) {
      document.getElementById('deleteId').textContent = id;
      document.getElementById('deleteFilename').value = filename;
      new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
  </script>
</body>

</html>

<?php
function h($str)
{
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>