-- ============================================================
-- SAGT Stores Repair Item Tracker - MySQL database
-- Database name : sagt_repair
--
-- HOW TO USE (XAMPP):
--   Option A: phpMyAdmin -> Import -> choose this file -> Go
--   Option B: do nothing. index.php imports this file automatically
--             the first time you open the app.
-- ============================================================

CREATE DATABASE IF NOT EXISTS sagt_repair CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sagt_repair;

-- ------------------------------------------------------------
-- 1. USERS  (the two logins of the app: stores / procurement)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  role       VARCHAR(30)  NOT NULL,
  full_name  VARCHAR(100) NOT NULL,
  password   VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. SUPPLIERS  (service providers list)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_supplier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. UOMS  (units of measure: Each, Set, Unit, Pcs ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uoms (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uom_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. REPAIR_ITEMS  (main table - one row per repair item)
--    Column groups follow the 3 screens of the app:
--    Dispatch (Stores) -> Procurement -> Receiving (Stores)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS repair_items (
  id                    VARCHAR(20)   NOT NULL,          -- REP-0001
  -- Dispatch
  gate_pass_no          VARCHAR(50)   NOT NULL DEFAULT '',
  wo_number             VARCHAR(100)  NOT NULL DEFAULT '',
  por_number            VARCHAR(100)  NOT NULL DEFAULT '',
  is_urgent             TINYINT(1)    NOT NULL DEFAULT 0,
  is_foc                TINYINT(1)    NOT NULL DEFAULT 0,   -- Warranty / FOC
  foc_master_wo         VARCHAR(100)  NOT NULL DEFAULT '',
  foc_description       TEXT          NULL,
  item_description      TEXT          NULL,
  serial_model_no       VARCHAR(100)  NOT NULL DEFAULT '',
  service_provider      VARCHAR(150)  NOT NULL DEFAULT '',
  dispatch_qty          DECIMAL(12,2) NOT NULL DEFAULT 1,
  dispatch_uom          VARCHAR(50)   NOT NULL DEFAULT 'Each',
  taken_by              VARCHAR(100)  NOT NULL DEFAULT '',
  dispatch_date         DATE          NULL,
  -- Procurement
  procurement_stage     VARCHAR(50)   NOT NULL DEFAULT 'Pending Quotation',
  procurement_completed TINYINT(1)    NOT NULL DEFAULT 0,
  po_number             VARCHAR(100)  NOT NULL DEFAULT '',
  quotation_rec_date    DATE          NULL,
  quotation_app_date    DATE          NULL,
  est_receive_date      DATE          NULL,
  procurement_remarks   TEXT          NULL,
  -- Receiving
  goods_received_date   DATE          NULL,
  received_this_time    DECIMAL(12,2) NOT NULL DEFAULT 0,
  received_qty          DECIMAL(12,2) NOT NULL DEFAULT 0,
  received_uom          VARCHAR(50)   NOT NULL DEFAULT 'Each',
  doc_ref_no            VARCHAR(100)  NOT NULL DEFAULT '',
  received_by           VARCHAR(100)  NOT NULL DEFAULT '',
  inspected_by          VARCHAR(100)  NOT NULL DEFAULT '',
  -- Overall status
  status                VARCHAR(50)   NOT NULL DEFAULT 'In Repair / Out at Supplier',
  sort_order            INT           NOT NULL DEFAULT 0,   -- keeps the same order as the app list
  created_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status   (status),
  KEY idx_stage    (procurement_stage),
  KEY idx_supplier (service_provider),
  KEY idx_gp       (gate_pass_no),
  KEY idx_wo       (wo_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. NOTIFICATIONS  (bell icon messages for each role)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id          BIGINT       NOT NULL,
  target_role VARCHAR(30)  NOT NULL,
  title       VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT         NULL,
  time_label  VARCHAR(50)  NOT NULL DEFAULT '',
  is_urgent   TINYINT(1)   NOT NULL DEFAULT 0,
  item_id     VARCHAR(20)  NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_role (target_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STARTING DATA (same as the sample data inside your frontend)
-- ============================================================

INSERT IGNORE INTO users (role, full_name, password) VALUES
  ('stores', 'Stores Officer', 'SAGTSTO@123'),
  ('procurement', 'Procurement Officer', 'SAGTPRC@456');

INSERT IGNORE INTO suppliers (name) VALUES
  ('A I Hightech'),
  ('A I Tech'),
  ('A.I HIGH TECH'),
  ('A.I.T.HIGHTECH ING'),
  ('AC Paul'),
  ('All work Engineering'),
  ('C WING'),
  ('Central Bearing'),
  ('Ceylon Auto'),
  ('D V MARTENSTYN'),
  ('DILAN MOTORS'),
  ('DIMO'),
  ('Dabare Electrical'),
  ('Damro'),
  ('Dynamic'),
  ('Dynamic Eng.Technology'),
  ('Fine Finish Engineering'),
  ('Green Auto'),
  ('Isuru Tharanga'),
  ('Jaya Marine'),
  ('KD Windscreen'),
  ('Kandu Radiator'),
  ('Kandy Radiator'),
  ('L P G ENGINEERING'),
  ('LPG'),
  ('Leyland'),
  ('Lotus'),
  ('MONARA'),
  ('Macksons'),
  ('Mayphil'),
  ('R & S INDUSTRIES'),
  ('R & S'),
  ('RODSON, RATHMALANA'),
  ('RS Engineering'),
  ('SIPTWO PLASTICS AND REPAIR'),
  ('Sathya cushion'),
  ('Sundry'),
  ('THUDAVE BROTHERS'),
  ('TPL'),
  ('Terms'),
  ('The Electric Company'),
  ('Thilaka Traders'),
  ('Tpl'),
  ('Trade Promoters'),
  ('Tudawe Brother'),
  ('UPALLI ELECTRICALS'),
  ('UT Engineering'),
  ('Viskam'),
  ('Viskam Brothers'),
  ('Viskum Brothers');

INSERT IGNORE INTO uoms (name) VALUES
  ('Each'),
  ('Set'),
  ('Unit'),
  ('Pcs');

INSERT IGNORE INTO repair_items (id, gate_pass_no, wo_number, por_number, is_urgent, is_foc, foc_master_wo, foc_description, item_description, serial_model_no, service_provider, dispatch_qty, dispatch_uom, taken_by, dispatch_date, procurement_stage, po_number, quotation_rec_date, quotation_app_date, est_receive_date, procurement_remarks, goods_received_date, received_this_time, received_qty, received_uom, doc_ref_no, received_by, inspected_by, status) VALUES
  ('REP-0001', '8822', 'WO2844100-FOC', 'POR-88100', 0, 1, 'FOC Repair 1', 'Warranty seal replacement', 'RTG Wheel Hub + Ring', '', 'A I Hightech', 1, 'Each', 'Viraj', '2026-03-09', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0002', '5462', 'WO2844309', 'POR-88120', 1, 0, '', '', 'Engine RT34 esn923216885', 'ESN923216885', 'Tpl', 1, 'Each', 'VIRAJ', '2026-01-06', 'Completed', 'PO-9921', '2026-01-10', '2026-01-14', '2026-03-10', 'Standard maintenance overhaul', '2026-03-13', 1, 1, 'Each', '17128', 'KALANA', 'Silva M.', 'Completed & Ready'),
  ('REP-0003', '5462', 'WO2844310', 'POR-88121', 0, 0, '', '', 'Engine RT37 esn93217134', 'ESN93217134', 'A.I HIGH TECH', 1, 'Each', 'VIRAJ', '2026-01-01', 'Pending Quotation Approval', '', '2026-01-05', NULL, '2026-01-25', '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0004', '12683', 'WO2844139', 'POR-88145', 1, 0, '', '', 'Differential Unit RT09', '', 'Leyland', 1, 'Each', 'VIRAJ', '2026-01-08', 'Pending Purchase Order', '', '2026-01-12', '2026-01-15', '2026-02-15', 'Approval received, PO being drafted', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0005', '12683', 'WO2844426-1', 'POR-88146', 0, 0, '', '', 'Hoist Sleeve', '', 'Fine Finish Engineering', 1, 'Each', 'VIRAJ', '2026-01-08', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0006', '12683', 'WO2844426-2', 'POR-88147', 0, 0, '', '', '5030 Bearing Assembly', '', 'Fine Finish Engineering', 1, 'Each', 'VIRAJ', '2026-01-08', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0007', '9470', 'WO2844531', 'POR-88155', 0, 0, '', '', 'Engine RT33 Overhaul', '', 'TPL', 1, 'Each', 'VIRAJ', '2026-01-09', 'Pending Purchase Order', '', '2026-01-18', '2026-01-22', '2026-02-20', 'Awaiting final PO issuance', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0008', '12686', 'WO2844528', 'POR-88160', 0, 0, '', '', 'SS SHEET Marine Spec', '', 'MONARA', 1, 'Each', 'VIRAJ', '2026-01-10', 'Pending Quotation Approval', '', '2026-01-12', NULL, '2026-01-20', 'Quotation submitted for review', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0009', '12690', 'WO2845449-1', 'POR-88200', 1, 0, '', '', 'Antisway Drum RTG A', '', 'Fine Finish Engineering', 1, 'Each', 'VIRAJ', '2026-01-19', 'Pending Quotation', '', NULL, NULL, '2026-02-01', '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0010', '12690', 'WO2845449-2', 'POR-88201', 0, 0, '', '', 'Antisway Drum RTG B', '', 'AC Paul', 1, 'Each', 'VIRAJ', '2026-01-19', 'Pending Quotation', '', NULL, NULL, '2026-02-04', '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0011', '12701', 'WO2845512', 'POR-88210', 0, 0, '', '', 'Hydraulic Cylinder RT02', 'HC-9921', 'AC Paul', 1, 'Each', 'VIRAJ', '2026-01-22', 'Pending Quotation Approval', '', '2026-01-25', NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0012', '12710', 'WO2845620', 'POR-88225', 0, 0, '', '', 'Spreader Cable Drum', '', 'All work Engineering', 1, 'Each', 'KALANA', '2026-01-28', 'Pending Purchase Order', '', '2026-02-01', '2026-02-05', '2026-03-01', '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0013', '12715', 'WO2845700', 'POR-88230', 0, 0, '', '', 'Gantry Drive Gearbox', 'GB-201', 'All work Engineering', 1, 'Each', 'KALANA', '2026-02-02', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0014', '12720', 'WO2845810', 'POR-88240', 0, 0, '', '', 'Radiator Assembly RT15', '', 'CRW Technologies', 1, 'Each', 'VIRAJ', '2026-02-05', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0015', '12725', 'WO2845920', 'POR-88250', 0, 0, '', '', 'Alternator 24V Heavy Duty', '', 'Sundry Supplier', 1, 'Each', 'KALANA', '2026-02-10', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier'),
  ('REP-0016', '12730', 'WO2846001', 'POR-88260', 0, 0, '', '', 'Main Hoist Brake Caliper', 'BC-881', 'A I Hightech', 1, 'Each', 'VIRAJ', '2026-02-15', 'Pending Quotation', '', NULL, NULL, NULL, '', NULL, 0, 0, 'Each', '', '', '', 'In Repair / Out at Supplier');

INSERT IGNORE INTO notifications (id, target_role, title, description, time_label, is_urgent, item_id) VALUES
  (1, 'procurement', 'Pending Quotation Review', 'WO2844100-FOC (RTG Wheel Hub + Ring) dispatched to A I Hightech.', 'Today', 0, 'REP-0001'),
  (2, 'stores', 'Delivery Ready', 'GP #5462 (WO2844309 Engine RT34) completed and ready at stores.', 'Yesterday', 1, 'REP-0002');
