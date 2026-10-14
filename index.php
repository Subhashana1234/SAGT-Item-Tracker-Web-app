<?php
/* ============================================================
   SAGT Stores Repair Item Tracker - PHP backend
   ------------------------------------------------------------
   * Serves your UNCHANGED index.html
   * Saves / loads the data in real MySQL tables:
       users, suppliers, uoms, repair_items, notifications
   * On first run it creates the database + tables + starting
     data automatically from database.sql (same folder)

   Open:  http://localhost/sagt%20repair%20items/
   ============================================================ */

// ---------- SETTINGS (XAMPP defaults) ----------
$DB_HOST = 'sql310.infinityfree.com';
$DB_USER = 'if0_43048922';
$DB_PASS = 'Sagt123456';              // XAMPP default is empty
$DB_NAME = 'if0_43048922_sagt_repair';
// -----------------------------------------------

// ---------- small helpers ----------
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cut($s, $len) {
    return function_exists('mb_substr') ? mb_substr($s, 0, $len, 'UTF-8') : substr($s, 0, $len);
}

// Item columns:  [db column, frontend key, type, max length / default number, default text]
function item_fields() {
    return [
        ['gate_pass_no',         'gatePassNo',         'str',  50,  ''],
        ['wo_number',            'woNumber',           'str',  100, ''],
        ['por_number',           'porNumber',          'str',  100, ''],
        ['is_urgent',            'isUrgent',           'bool'],
        ['is_foc',               'isFoc',              'bool'],
        ['foc_master_wo',        'focMasterWo',        'str',  100, ''],
        ['foc_description',      'focDescription',     'text'],
        ['item_description',     'itemDescription',    'text'],
        ['serial_model_no',      'serialModelNo',      'str',  100, ''],
        ['service_provider',     'serviceProvider',    'str',  150, ''],
        ['dispatch_qty',         'dispatchQty',        'num',  1],
        ['dispatch_uom',         'dispatchUom',        'str',  50,  'Each'],
        ['taken_by',             'takenBy',            'str',  100, ''],
        ['dispatch_date',        'dispatchDate',       'date'],
        ['procurement_stage',    'procurementStage',   'str',  50,  'Pending Quotation'],
        ['procurement_completed','procurementCompleted','bool'],
        ['po_number',            'poNumber',           'str',  100, ''],
        ['quotation_rec_date',   'quotationRecDate',   'date'],
        ['quotation_app_date',   'quotationAppDate',   'date'],
        ['est_receive_date',     'estReceiveDate',     'date'],
        ['procurement_remarks',  'procurementRemarks', 'text'],
        ['goods_received_date',  'goodsReceivedDate',  'date'],
        ['received_this_time',   'receivedThisTime',   'num',  0],
        ['received_qty',         'receivedQty',        'num',  0],
        ['received_uom',         'receivedUom',        'str',  50,  ''],
        ['doc_ref_no',           'docRefNo',           'str',  100, ''],
        ['received_by',          'receivedBy',         'str',  100, ''],
        ['inspected_by',         'inspectedBy',        'str',  100, ''],
        ['status',               'status',             'str',  50,  'In Repair / Out at Supplier'],
    ];
}

// frontend value -> database value
function to_db($f, $v) {
    $type = $f[2];
    if ($type === 'bool') {
        return ($v === true || $v === 1 || $v === '1' || $v === 'true') ? 1 : 0;
    }
    if ($type === 'date') {
        if (is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return $v;
        }
        return null;
    }
    if ($type === 'num') {
        return is_numeric($v) ? (float)$v : (float)$f[3];
    }
    if ($type === 'text') {
        return is_scalar($v) ? (string)$v : '';
    }
    $s = is_scalar($v) ? (string)$v : '';
    return $s === '' ? $f[4] : cut($s, $f[3]);
}

// database value -> frontend value
function from_db($f, $v) {
    $type = $f[2];
    if ($type === 'bool') return (bool)$v;
    if ($type === 'num')  { $n = (float)$v; return floor($n) == $n ? (int)$n : $n; }
    return $v === null ? '' : (string)$v;
}

// ---------- database connection + auto install ----------
function get_db($host, $user, $pass, $name) {
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
    } catch (PDOException $e) {
        // 1049 = database does not exist yet -> create it
        if (strpos($e->getMessage(), '1049') === false) throw $e;
        $tmp = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, $opts);
        $tmp->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
    }
    install_if_needed($pdo);
    return $pdo;
}

function install_if_needed($pdo) {
    $need = false;
    foreach (['users', 'suppliers', 'uoms', 'repair_items', 'notifications'] as $t) {
        if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetch()) { $need = true; break; }
    }
    if (!$need) return;

    $file = __DIR__ . '/database.sql';
    if (!is_file($file)) throw new Exception('database.sql not found. Put it in the same folder as index.php');

    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);            // remove comment lines
    foreach (preg_split('/;\s*[\r\n]+/', $sql) as $stmt) {
        $stmt = trim($stmt);
        $stmt = rtrim($stmt, ";\r\n\t ");
        if ($stmt === '') continue;
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $stmt)) continue;   // database already selected
        $pdo->exec($stmt);
    }
}

// ---------- LOAD: tables -> the format the frontend expects ----------
function load_all($pdo) {
    $json = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $fields = item_fields();

    $items = [];
    foreach ($pdo->query("SELECT * FROM repair_items ORDER BY sort_order ASC, id ASC") as $r) {
        $o = ['id' => $r['id']];
        foreach ($fields as $f) $o[$f[1]] = from_db($f, $r[$f[0]]);
        $items[] = $o;
    }

    $suppliers = $pdo->query("SELECT name FROM suppliers ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $uoms      = $pdo->query("SELECT name FROM uoms ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

    $pw = [];
    foreach ($pdo->query("SELECT role, password FROM users") as $r) $pw[$r['role']] = $r['password'];

    $notes = [];
    foreach ($pdo->query("SELECT * FROM notifications ORDER BY sort_order ASC, id ASC") as $r) {
        $notes[] = [
            'id'         => (int)$r['id'],
            'targetRole' => $r['target_role'],
            'title'      => $r['title'],
            'desc'       => (string)$r['description'],
            'time'       => $r['time_label'],
            'isUrgent'   => (bool)$r['is_urgent'],
            'itemId'     => $r['item_id'],
        ];
    }
    $notesJson = json_encode($notes, $json);

    return [
        'sagt_items_v4'         => json_encode($items, $json),
        'sagt_suppliers_v4'     => json_encode(array_values($suppliers), $json),
        'sagt_uoms_v4'          => json_encode(array_values($uoms), $json),
        'sagt_passwords_v4'     => json_encode((object)$pw, $json),
        'sagt_notifications_v5' => $notesJson,
        'sagt_notifications_v4' => $notesJson,
    ];
}

// ---------- SAVE: frontend data -> tables ----------
function sync_items($pdo, $arr) {
    $fields = item_fields();

    $cols = ['id'];
    foreach ($fields as $f) $cols[] = $f[0];
    $cols[] = 'sort_order';
    $marks  = implode(',', array_fill(0, count($cols), '?'));
    $update = [];
    foreach ($cols as $c) if ($c !== 'id') $update[] = "$c = VALUES($c)";
    $stmt = $pdo->prepare('INSERT INTO repair_items (' . implode(',', $cols) . ") VALUES ($marks) ON DUPLICATE KEY UPDATE " . implode(',', $update));

    // highest REP number (used only if two items ever share the same id)
    $maxNum = 0;
    foreach ($arr as $it) {
        if (is_array($it) && isset($it['id']) && preg_match('/(\d+)$/', (string)$it['id'], $m)) $maxNum = max($maxNum, (int)$m[1]);
    }

    $pdo->beginTransaction();
    try {
        $seen = [];
        $idx  = 0;
        foreach ($arr as $it) {
            if (!is_array($it)) continue;
            $id = cut(isset($it['id']) ? (string)$it['id'] : '', 20);
            if ($id === '' || isset($seen[$id])) {
                $maxNum++;
                $id = 'REP-' . str_pad((string)$maxNum, 4, '0', STR_PAD_LEFT);
            }
            $seen[$id] = true;

            $vals = [$id];
            foreach ($fields as $f) $vals[] = to_db($f, isset($it[$f[1]]) ? $it[$f[1]] : null);
            $vals[] = $idx++;
            $stmt->execute($vals);
        }

        // remove items that were deleted in the app
        $ids = array_keys($seen);
        if (count($ids) > 0) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM repair_items WHERE id NOT IN ($in)")->execute($ids);
        } else {
            $pdo->exec("DELETE FROM repair_items");
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function sync_list($pdo, $table, $arr, $maxLen) {
    if ($table !== 'suppliers' && $table !== 'uoms') return;
    $names = [];
    foreach ($arr as $n) {
        if (!is_scalar($n)) continue;
        $n = cut((string)$n, $maxLen);
        if ($n !== '' && !in_array($n, $names, true)) $names[] = $n;
    }
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT IGNORE INTO $table (name) VALUES (?)");
        foreach ($names as $n) $ins->execute([$n]);

        $del = $pdo->prepare("DELETE FROM $table WHERE name = ?");
        foreach ($pdo->query("SELECT name FROM $table")->fetchAll(PDO::FETCH_COLUMN) as $existing) {
            if (!in_array($existing, $names, true)) $del->execute([$existing]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function sync_users($pdo, $obj) {
    $names = ['stores' => 'Stores Officer', 'procurement' => 'Procurement Officer'];
    $stmt = $pdo->prepare("INSERT INTO users (role, full_name, password) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE password = VALUES(password)");
    foreach ($obj as $role => $pass) {
        $role = (string)$role;
        if (!preg_match('/^[A-Za-z0-9_]{1,30}$/', $role) || !is_string($pass) || $pass === '') continue;
        $stmt->execute([$role, isset($names[$role]) ? $names[$role] : ucfirst($role), cut($pass, 255)]);
    }
}

function sync_notifications($pdo, $arr) {
    $stmt = $pdo->prepare("INSERT INTO notifications (id, target_role, title, description, time_label, is_urgent, item_id, sort_order)
                           VALUES (?,?,?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE target_role = VALUES(target_role), title = VALUES(title),
                           description = VALUES(description), time_label = VALUES(time_label),
                           is_urgent = VALUES(is_urgent), item_id = VALUES(item_id), sort_order = VALUES(sort_order)");
    $pdo->beginTransaction();
    try {
        $seen = [];
        $idx  = 0;
        foreach ($arr as $n) {
            if (!is_array($n)) continue;
            $id = isset($n['id']) && is_numeric($n['id']) ? (int)$n['id'] : (int)(microtime(true) * 1000) + $idx;
            while (isset($seen[$id])) $id++;
            $seen[$id] = true;
            $g = function ($k) use ($n) { return isset($n[$k]) && is_scalar($n[$k]) ? (string)$n[$k] : ''; };
            $stmt->execute([
                $id,
                cut($g('targetRole'), 30),
                cut($g('title'), 255),
                $g('desc'),
                cut($g('time'), 50),
                (!empty($n['isUrgent']) && $n['isUrgent'] !== 'false') ? 1 : 0,
                cut($g('itemId'), 20),
                $idx++,
            ]);
        }
        $ids = array_keys($seen);
        if (count($ids) > 0) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM notifications WHERE id NOT IN ($in)")->execute($ids);
        } else {
            $pdo->exec("DELETE FROM notifications");
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// =============== API PART ===============
if (isset($_GET['api'])) {
    $action = $_GET['api'];
    try {
        $pdo = get_db($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($action === 'ping') {
            json_out(['ok' => true, 'message' => 'Database connected']);
        }

        if ($action === 'load') {
            json_out(['ok' => true, 'data' => load_all($pdo)]);
        }

        if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $body  = json_decode(file_get_contents('php://input'), true);
            $key   = is_array($body) && isset($body['key']) ? $body['key'] : '';
            $value = is_array($body) && isset($body['value']) ? $body['value'] : null;
            $data  = is_string($value) ? json_decode($value, true) : null;

            if (!is_array($data)) json_out(['ok' => false, 'error' => 'Invalid data'], 400);

            if     ($key === 'sagt_items_v4')                                          sync_items($pdo, $data);
            elseif ($key === 'sagt_suppliers_v4')                                      sync_list($pdo, 'suppliers', $data, 150);
            elseif ($key === 'sagt_uoms_v4')                                           sync_list($pdo, 'uoms', $data, 50);
            elseif ($key === 'sagt_passwords_v4')                                      sync_users($pdo, $data);
            elseif ($key === 'sagt_notifications_v5' || $key === 'sagt_notifications_v4') sync_notifications($pdo, $data);
            else json_out(['ok' => false, 'error' => 'Unknown key'], 400);

            json_out(['ok' => true]);
        }

        json_out(['ok' => false, 'error' => 'Unknown action'], 400);

    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// =============== PAGE PART ===============
$file = __DIR__ . '/index.html';
if (!is_file($file)) {
    http_response_code(500);
    echo 'index.html not found. Put index.php, index.html and database.sql in the same folder.';
    exit;
}

$html = file_get_contents($file);

// Small script added at runtime (your index.html file is NOT modified).
// 1) Loads the data from MySQL before the app starts
// 2) Sends every change the app makes back to MySQL
$inject = <<<'JS'
<script>
(function () {
  var API = 'index.php?api=';
  var SAVE_KEYS = ['sagt_items_v4', 'sagt_suppliers_v4', 'sagt_uoms_v4', 'sagt_passwords_v4', 'sagt_notifications_v5'];
  var dbOk = true, lastSaved = {}, pending = {}, timers = {};
  var origSet = Storage.prototype.setItem;

  function banner(msg) {
    function show() {
      var b = document.getElementById('sagt-db-banner');
      if (!b) {
        b = document.createElement('div');
        b.id = 'sagt-db-banner';
        b.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#be123c;color:#fff;' +
                          'font:600 13px sans-serif;padding:8px 12px;text-align:center';
        document.body.appendChild(b);
      }
      b.textContent = msg;
    }
    if (document.body) show(); else document.addEventListener('DOMContentLoaded', show);
  }

  // ---- 1) Load from MySQL (runs before the React app starts) ----
  try {
    var x = new XMLHttpRequest();
    x.open('GET', API + 'load&_=' + Date.now(), false);
    x.send();
    var r = JSON.parse(x.responseText);
    if (x.status === 200 && r.ok) {
      for (var k in r.data) {
        origSet.call(window.localStorage, k, r.data[k]);
        lastSaved[k] = r.data[k];
      }
    } else {
      dbOk = false;
      banner('Database error: ' + (r.error || 'unknown') + ' - changes are NOT being saved.');
    }
  } catch (e) {
    dbOk = false;
    banner('Database not connected - start Apache and MySQL in XAMPP, then open this page again.');
  }

  // ---- 2) Save every change to MySQL ----
  function send(key, keepalive) {
    if (!(key in pending)) return;
    var value = pending[key];
    delete pending[key];
    fetch(API + 'save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: key, value: value }),
      keepalive: !!keepalive
    }).then(function (res) { return res.json(); })
      .then(function (j) {
        if (j && j.ok) lastSaved[key] = value;
        else banner('Save failed: ' + ((j && j.error) || 'unknown error'));
      })
      .catch(function () { banner('Save failed - is MySQL still running?'); });
  }

  Storage.prototype.setItem = function (key, value) {
    origSet.apply(this, arguments);
    if (this !== window.localStorage || !dbOk) return;
    var kk = (key === 'sagt_notifications_v4') ? null : key;
    if (!kk || SAVE_KEYS.indexOf(kk) === -1) return;
    var v = String(value);
    if (lastSaved[kk] === v) return;
    pending[kk] = v;
    clearTimeout(timers[kk]);
    timers[kk] = setTimeout(function () { send(kk, false); }, 300);
  };

  window.addEventListener('pagehide', function () {
    for (var k in pending) send(k, true);
  });
})();
</script>
JS;

// Put the script at the top of <head> so it runs before the app
$pos = stripos($html, '<head>');
if ($pos !== false) {
    $html = substr_replace($html, '<head>' . $inject, $pos, strlen('<head>'));
} else {
    $html = $inject . $html;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $html;
