<?php
/**
 * Kitchen notes pin-board.
 *   GET                       → recent notes (newest first)
 *   POST {action:'add', note, scope?, ref_id?}
 *   POST {action:'delete', id}
 */
require_once __DIR__ . '/_api_guard.php';

const VALID_NOTE_SCOPE = ['kitchen', 'order', 'table', 'menu'];

if (api_is_post()) {
    $input  = api_input();
    api_require_csrf($input);
    $action = trim((string)($input['action'] ?? 'add'));

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            api_json(['success' => false, 'message' => 'Invalid note']);
        }
        $del = $conn->prepare("DELETE FROM kitchen_notes WHERE id = ?");
        $del->bind_param('i', $id);
        $ok = $del->execute();
        $del->close();
        api_json(['success' => (bool)$ok, 'message' => $ok ? 'Note deleted' : 'Delete failed']);
    }

    // add
    $note  = trim((string)($input['note'] ?? ''));
    $scope = trim((string)($input['scope'] ?? 'kitchen'));
    $ref   = trim((string)($input['ref_id'] ?? ''));
    if ($note === '') {
        api_json(['success' => false, 'message' => 'Note text is required']);
    }
    if (!in_array($scope, VALID_NOTE_SCOPE, true)) {
        $scope = 'kitchen';
    }
    $refVal = $ref !== '' ? $ref : null;
    $uid    = (int)$panelUser['id'];
    $uname  = (string)($panelUser['name'] ?? 'Chef');

    $ins = $conn->prepare("INSERT INTO kitchen_notes (note, scope, ref_id, created_by, created_by_name)
                           VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param('sssis', $note, $scope, $refVal, $uid, $uname);
    if (!$ins->execute()) {
        api_json(['success' => false, 'message' => 'Failed to save note']);
    }
    $newId = $ins->insert_id;
    $ins->close();
    api_json(['success' => true, 'message' => 'Note added', 'id' => $newId]);
}

// GET list
$notes = [];
$res = $conn->query("SELECT id, note, scope, ref_id, created_by_name, created_at
                     FROM kitchen_notes ORDER BY created_at DESC, id DESC LIMIT 100");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notes[] = [
            'id'         => (int)$row['id'],
            'note'       => $row['note'],
            'scope'      => $row['scope'],
            'ref_id'     => $row['ref_id'],
            'author'     => $row['created_by_name'] ?: 'Kitchen',
            'created_at' => $row['created_at'],
        ];
    }
}
api_json(['success' => true, 'notes' => $notes]);
