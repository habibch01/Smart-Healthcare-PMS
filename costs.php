<?php
require_once "config/database.php";

$message = "";
$messageType = "success";

// Get all tasks
$taskStmt = $pdo->query(
    "SELECT t.id, t.task_name, t.project_id, p.project_name
     FROM tasks t
     INNER JOIN projects p ON t.project_id = p.id
     ORDER BY p.project_name, t.task_name"
);

$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Save or update cost information
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $taskId = (int)($_POST["task_id"] ?? 0);
    $plannedCost = $_POST["planned_cost"] ?? "";
    $actualCost = $_POST["actual_cost"] ?? "";
    $earnedValue = $_POST["earned_value"] ?? "";

    $validTask = false;

    foreach ($tasks as $task) {
        if ((int)$task["id"] === $taskId) {
            $validTask = true;
            break;
        }
    }

    $validNumbers = (
        $plannedCost !== "" &&
        $actualCost !== "" &&
        $earnedValue !== "" &&
        is_numeric($plannedCost) &&
        is_numeric($actualCost) &&
        is_numeric($earnedValue) &&
        is_finite((float)$plannedCost) &&
        is_finite((float)$actualCost) &&
        is_finite((float)$earnedValue) &&
        (float)$plannedCost >= 0 &&
        (float)$actualCost >= 0 &&
        (float)$earnedValue >= 0 &&
        (float)$plannedCost <= 9999999999.99 &&
        (float)$actualCost <= 9999999999.99 &&
        (float)$earnedValue <= 9999999999.99
    );

    if (!$validTask || !$validNumbers) {
        $message = "Please select a task and enter valid cost values.";
        $messageType = "error";
    } else {
        // Check whether this task already has a cost record
        $checkStmt = $pdo->prepare(
            "SELECT id FROM task_costs WHERE task_id = ? LIMIT 1"
        );

        $checkStmt->execute([$taskId]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            // Update existing record instead of creating a duplicate
            $saveStmt = $pdo->prepare(
                "UPDATE task_costs
                 SET planned_cost = ?,
                     actual_cost = ?,
                     earned_value = ?
                 WHERE id = ?"
            );

            $saveStmt->execute([
                $plannedCost,
                $actualCost,
                $earnedValue,
                $existingId
            ]);
        } else {
            // Insert a new cost record
            $saveStmt = $pdo->prepare(
                "INSERT INTO task_costs
                 (task_id, planned_cost, actual_cost, earned_value)
                 VALUES (?, ?, ?, ?)"
            );

            $saveStmt->execute([
                $taskId,
                $plannedCost,
                $actualCost,
                $earnedValue
            ]);
        }

        $message = "Cost information saved successfully!";
    }
}

// Get cost totals for each project
$summaryStmt = $pdo->query(
    "SELECT
        p.id AS project_id,
        p.project_name,
        p.approved_budget,
        COALESCE(SUM(tc.planned_cost), 0) AS pv,
        COALESCE(SUM(tc.earned_value), 0) AS ev,
        COALESCE(SUM(tc.actual_cost), 0) AS ac
     FROM projects p
     LEFT JOIN tasks t ON t.project_id = p.id
     LEFT JOIN task_costs tc ON tc.task_id = t.id
     GROUP BY p.id, p.project_name, p.approved_budget
     ORDER BY p.id DESC"
);

$projectSummaries = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

// Get cost details for each task
$detailsStmt = $pdo->query(
    "SELECT
        t.id AS task_id,
        t.task_name,
        p.project_name,
        tc.planned_cost,
        tc.earned_value,
        tc.actual_cost
     FROM tasks t
     INNER JOIN projects p ON p.id = t.project_id
     LEFT JOIN task_costs tc ON tc.task_id = t.id
     ORDER BY p.project_name, t.id DESC"
);

$costDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

// Display totals (UI only)
$totalBudget = 0.0;
$totalPv = 0.0;
$totalEv = 0.0;
$totalAc = 0.0;

foreach ($projectSummaries as $s) {
    $totalBudget += (float)$s["approved_budget"];
    $totalPv += (float)$s["pv"];
    $totalEv += (float)$s["ev"];
    $totalAc += (float)$s["ac"];
}

function indexPill($value)
{
    if ($value === null) {
        return '<span class="pill pill-muted">N/A</span>';
    }

    $class = $value >= 1 ? "pill-green" : "pill-red";
    $label = $value >= 1 ? "On track" : "Needs attention";

    return '<span class="pill ' . $class . '">' . $label . '</span>';
}

function costCell($value)
{
    if ($value === null) {
        return '<span class="cell-muted">Not Entered</span>';
    }

    return '<span class="money">' . number_format((float)$value, 2) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cost Management | Smart Healthcare PMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --purple: #8b5cf6;
            --navy: #111b2e;
            --background: #f5f7ff;
            --text: #202b46;
            --muted: #8490a5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top right, #e8eaff 0, transparent 28%),
                var(--background);
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
        }

        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #111827, #192b49);
            padding: 28px 15px;
            box-shadow: 5px 0 25px rgba(15, 23, 42, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: white;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.4;
            margin: 0 5px 30px;
        }

        .brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 43px;
            height: 43px;
            border-radius: 13px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            font-size: 23px;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
        }

        .menu-heading {
            color: #71829e;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 18px 12px 9px;
            letter-spacing: 1.5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #aebbd0;
            text-decoration: none;
            padding: 12px 13px;
            border-radius: 10px;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .sidebar a i {
            font-size: 16px;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateX(3px);
        }

        .sidebar a.active {
            background: linear-gradient(110deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
        }

        /* Main content */
        .main-content {
            padding: 32px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.8px;
            color: #17213a;
            margin: 0 0 6px;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            border-radius: 30px;
            background: #e8f9ef;
            color: #16834a;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-dot {
            height: 7px;
            width: 7px;
            background: #22c55e;
            border-radius: 50%;
        }

        .section-heading {
            font-size: 16px;
            font-weight: 800;
            color: #26334d;
            margin-bottom: 15px;
        }

        /* Alerts */
        .alert-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .alert-box i {
            font-size: 18px;
        }

        .alert-success-soft {
            background: #e8f9ef;
            color: #16834a;
            border: 1px solid #c7eed7;
        }

        .alert-error-soft {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fbcaca;
        }

        .alert-warning-soft {
            background: #ffedd5;
            color: #c2410c;
            border: 1px solid #fcd9b0;
        }

        /* Stat cards */
        .stat-card {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid #edf0f7;
            border-radius: 17px;
            padding: 21px;
            height: 100%;
            box-shadow: 0 6px 24px rgba(31, 41, 55, 0.04);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .stat-card:hover,
        .panel:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(31, 41, 55, 0.08);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .stat-card h6 {
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 21px;
        }

        .icon-blue {
            color: #4f46e5;
            background: #eeedff;
        }

        .icon-green {
            color: #059669;
            background: #dcfce7;
        }

        .icon-orange {
            color: #ea580c;
            background: #ffedd5;
        }

        .icon-red {
            color: #dc2626;
            background: #fee2e2;
        }

        .stat-card h3 {
            font-size: 24px;
            font-weight: 850;
            color: #202b46;
            margin: 18px 0 5px;
            letter-spacing: -0.6px;
        }

        .stat-note {
            color: var(--muted);
            font-size: 11px;
        }

        /* Panels */
        .panel {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #edf0f7;
            border-radius: 17px;
            padding: 24px;
            height: 100%;
            box-shadow: 0 6px 24px rgba(31, 41, 55, 0.04);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .panel-title {
            font-size: 15px;
            font-weight: 800;
            color: #26334d;
            margin: 0;
        }

        .panel-subtitle {
            color: var(--muted);
            font-size: 12px;
            margin: 7px 0 20px;
        }

        /* Forms */
        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #3a4763;
            margin-bottom: 7px;
        }

        .form-control,
        .form-select {
            border: 1px solid #e3e7f1;
            border-radius: 10px;
            padding: 11px 13px;
            font-size: 13px;
            color: var(--text);
            background-color: #fbfcff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control::placeholder {
            color: #a4aec0;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

        .btn-gradient {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(110deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 11px 22px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-gradient:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(99, 102, 241, 0.38);
        }

        /* Formula guide */
        .formula-box {
            background: #f6f7fd;
            border: 1px dashed #d6daf0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 700;
            color: #293650;
            margin-bottom: 12px;
        }

        .guide-note {
            color: var(--muted);
            font-size: 12px;
            margin: 14px 0 0;
            line-height: 1.6;
        }

        /* Table */
        .table-wrap {
            overflow-x: auto;
        }

        .pms-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .pms-table thead th {
            background: #f6f7fd;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 13px 15px;
            border-bottom: 1px solid #edf0f7;
            white-space: nowrap;
            text-align: left;
        }

        .pms-table thead th:first-child {
            border-top-left-radius: 10px;
        }

        .pms-table thead th:last-child {
            border-top-right-radius: 10px;
        }

        .pms-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #f0f2f8;
            vertical-align: middle;
            color: #293650;
        }

        .pms-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pms-table tbody tr:hover td {
            background: #fafaff;
        }

        .row-id {
            color: var(--muted);
            font-weight: 700;
        }

        .cell-strong {
            font-weight: 700;
            color: #1f2b46;
        }

        .cell-muted {
            color: var(--muted);
            font-size: 12px;
        }

        .money {
            font-weight: 700;
            white-space: nowrap;
        }

        .project-chip {
            display: inline-block;
            background: #eeedff;
            color: #4f46e5;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
        }

        .index-value {
            font-weight: 800;
            font-size: 14px;
            margin-right: 6px;
        }

        /* Pills */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pill::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .pill-green {
            background: #e8f9ef;
            color: #16834a;
        }

        .pill-red {
            background: #fee2e2;
            color: #b91c1c;
        }

        .pill-muted {
            background: #eef0f6;
            color: #6b7690;
        }

        .empty-state {
            text-align: center;
            padding: 40px 15px !important;
            color: var(--muted) !important;
        }

        .empty-state i {
            display: block;
            font-size: 30px;
            margin-bottom: 8px;
            color: #c5cbe0;
        }

        .footer {
            text-align: center;
            color: #9aa4b6;
            font-size: 11px;
            padding: 25px 0 5px;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
                padding: 20px 14px;
            }

            .brand {
                margin-bottom: 18px;
            }

            .main-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 23px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .panel {
                padding: 20px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-card h3 {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 sidebar">

            <div class="brand">
                <div class="brand-icon">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <div>
                    Smart Healthcare
                    <div style="font-size: 11px; color: #9eacc4; font-weight: 500;">
                        Project Management
                    </div>
                </div>
            </div>

            <a href="dashboard.php">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>

            <div class="menu-heading">Project Planning</div>

            <a href="projects.php">
                <i class="bi bi-folder2-open"></i> Project Initiation
            </a>

            <a href="requirements.php">
                <i class="bi bi-card-checklist"></i> Scope & Requirements
            </a>

            <a href="wbs.php">
                <i class="bi bi-diagram-3"></i> Scope & WBS
            </a>

            <a href="schedule.php">
                <i class="bi bi-calendar3"></i> Schedule & Gantt
            </a>

            <a href="team.php">
                <i class="bi bi-people"></i> Team & Resources
            </a>

            <div class="menu-heading">Project Control</div>

            <a class="active" href="costs.php">
                <i class="bi bi-currency-rupee"></i> Cost Management
            </a>

            <a href="quality.php">
                <i class="bi bi-patch-check"></i> Quality & Defects
            </a>

            <a href="risks.php">
                <i class="bi bi-shield-exclamation"></i> Risk Management
            </a>

            <a href="change_requests.php">
                <i class="bi bi-arrow-left-right"></i> Change Requests
            </a>

            <div class="menu-heading">Monitoring</div>

            <a href="reports.php">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>

        </aside>

        <!-- Main Content -->
        <main class="col-md-9 col-lg-10 main-content">

            <!-- Header -->
            <div class="topbar">
                <div>
                    <h1 class="page-title">Cost Management &amp; Earned Value Analysis</h1>
                    <p class="page-subtitle">
                        Track planned costs, actual costs, and project performance.
                    </p>
                </div>

                <div class="status-badge">
                    <span class="status-dot"></span>
                    <?= count($projectSummaries) ?> Projects Tracked
                </div>
            </div>

            <!-- Message -->
            <?php if ($message !== ""): ?>
                <div class="alert-box <?= $messageType === 'error' ? 'alert-error-soft' : 'alert-success-soft' ?>">
                    <i class="bi <?= $messageType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Cost totals -->
            <h5 class="section-heading">Cost at a Glance</h5>

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Approved Budget</h6>
                            <div class="stat-icon icon-blue">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                        <h3>PKR <?= number_format($totalBudget, 0) ?></h3>
                        <div class="stat-note">Across all projects</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Planned Value (PV)</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-clipboard-data"></i>
                            </div>
                        </div>
                        <h3>PKR <?= number_format($totalPv, 0) ?></h3>
                        <div class="stat-note">Budgeted value of planned work</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Earned Value (EV)</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                        <h3>PKR <?= number_format($totalEv, 0) ?></h3>
                        <div class="stat-note">Budgeted value of completed work</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Actual Cost (AC)</h6>
                            <div class="stat-icon icon-red">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                        <h3>PKR <?= number_format($totalAc, 0) ?></h3>
                        <div class="stat-note">Actual amount spent</div>
                    </div>
                </div>

            </div>

            <?php if (count($tasks) === 0): ?>

                <div class="alert-box alert-warning-soft">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Please add a task in Schedule &amp; Resource Management first.
                </div>

            <?php else: ?>

            <!-- Form and Guide -->
            <div class="row g-3 mb-4">

                <div class="col-lg-8">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-plus-circle text-primary me-2"></i>
                            Enter Task Cost Information
                        </h5>

                        <p class="panel-subtitle">
                            Saving again for the same task updates its existing cost record.
                        </p>

                        <form method="POST">

                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="form-label">Select Task</label>
                                    <select name="task_id" class="form-select" required>
                                        <option value="">Choose a task</option>
                                        <?php foreach ($tasks as $task): ?>
                                            <option value="<?= (int)$task["id"] ?>">
                                                <?= htmlspecialchars(
                                                    $task["project_name"] . " - " .
                                                    $task["task_name"]
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Planned Cost (PV) - PKR</label>
                                    <input type="number"
                                           name="planned_cost"
                                           class="form-control"
                                           min="0"
                                           max="9999999999.99"
                                           step="0.01"
                                           required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Actual Cost (AC) - PKR</label>
                                    <input type="number"
                                           name="actual_cost"
                                           class="form-control"
                                           min="0"
                                           max="9999999999.99"
                                           step="0.01"
                                           required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Earned Value (EV) - PKR</label>
                                    <input type="number"
                                           name="earned_value"
                                           class="form-control"
                                           min="0"
                                           max="9999999999.99"
                                           step="0.01"
                                           required>
                                </div>

                            </div>

                            <button type="submit" class="btn-gradient mt-4">
                                <i class="bi bi-check2-circle"></i>
                                Save Cost Information
                            </button>

                        </form>

                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-info-circle text-primary me-2"></i>
                            Formula Guide
                        </h5>

                        <p class="panel-subtitle">
                            How the performance indexes are calculated.
                        </p>

                        <div class="formula-box">CPI = EV / AC</div>
                        <div class="formula-box">SPI = EV / PV</div>

                        <p class="guide-note">
                            If AC or PV is zero, the corresponding index is shown as N/A.
                            A value of 1 or above means the project is on track.
                        </p>

                    </section>
                </div>

            </div>

            <?php endif; ?>

            <!-- Earned Value Analysis -->
            <section class="panel mb-4">

                <h5 class="panel-title">
                    <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                    Earned Value Analysis
                </h5>

                <p class="panel-subtitle">
                    All amounts are calculated from the cost records saved for tasks
                    belonging to each project.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Approved Budget (PKR)</th>
                                <th>PV (PKR)</th>
                                <th>EV (PKR)</th>
                                <th>AC (PKR)</th>
                                <th>CPI</th>
                                <th>SPI</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($projectSummaries as $summary): ?>

                                <?php
                                $pv = (float)$summary["pv"];
                                $ev = (float)$summary["ev"];
                                $ac = (float)$summary["ac"];

                                $cpi = $ac > 0 ? $ev / $ac : null;
                                $spi = $pv > 0 ? $ev / $pv : null;
                                ?>

                                <tr>
                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($summary["project_name"]) ?>
                                        </span>
                                    </td>

                                    <td class="money">
                                        <?= number_format((float)$summary["approved_budget"], 2) ?>
                                    </td>

                                    <td class="money"><?= number_format($pv, 2) ?></td>
                                    <td class="money"><?= number_format($ev, 2) ?></td>
                                    <td class="money"><?= number_format($ac, 2) ?></td>

                                    <td>
                                        <span class="index-value">
                                            <?= $cpi !== null ? number_format($cpi, 2) : "N/A" ?>
                                        </span>
                                        <?= indexPill($cpi) ?>
                                    </td>

                                    <td>
                                        <span class="index-value">
                                            <?= $spi !== null ? number_format($spi, 2) : "N/A" ?>
                                        </span>
                                        <?= indexPill($spi) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (count($projectSummaries) === 0): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No projects found.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- Task Cost Details -->
            <section class="panel">

                <h5 class="panel-title">
                    <i class="bi bi-list-check text-primary me-2"></i>
                    Task Cost Details
                </h5>

                <p class="panel-subtitle">
                    Planned, earned, and actual cost for each task.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Project</th>
                                <th>Task Name</th>
                                <th>PV (PKR)</th>
                                <th>EV (PKR)</th>
                                <th>AC (PKR)</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($costDetails as $detail): ?>
                                <tr>
                                    <td class="row-id">#<?= (int)$detail["task_id"] ?></td>

                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($detail["project_name"]) ?>
                                        </span>
                                    </td>

                                    <td class="cell-strong">
                                        <?= htmlspecialchars($detail["task_name"]) ?>
                                    </td>

                                    <td><?= costCell($detail["planned_cost"]) ?></td>
                                    <td><?= costCell($detail["earned_value"]) ?></td>
                                    <td><?= costCell($detail["actual_cost"]) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($costDetails) === 0): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No tasks available.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </section>

            <!-- Footer -->
            <div class="footer">
                Smart Healthcare PMS &nbsp;•&nbsp; Project Monitoring Dashboard
            </div>

        </main>

    </div>
</div>

</body>
</html>