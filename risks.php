<?php
require_once "config/database.php";

$message = "";
$messageType = "success";

// Get all projects
$projectsStmt = $pdo->query(
    "SELECT id, project_name
     FROM projects
     ORDER BY id DESC"
);

$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);


// Save risk when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $projectId = (int)($_POST["project_id"] ?? 0);
    $riskTitle = trim($_POST["risk_title"] ?? "");
    $likelihood = (int)($_POST["likelihood"] ?? 0);
    $impact = (int)($_POST["impact"] ?? 0);
    $responsePlan = trim($_POST["response_plan"] ?? "");
    $status = $_POST["status"] ?? "Open";

    // Check whether the selected project exists
    $validProject = false;

    foreach ($projects as $project) {
        if ((int)$project["id"] === $projectId) {
            $validProject = true;
            break;
        }
    }

    // Allowed risk statuses
    $validStatuses = ["Open", "Monitoring", "Closed"];

    if (!$validProject) {

        $message = "Please select a valid project.";
        $messageType = "error";

    } elseif (
        $riskTitle === "" ||
        strlen($riskTitle) > 200 ||
        $likelihood < 1 ||
        $likelihood > 5 ||
        $impact < 1 ||
        $impact > 5 ||
        !in_array($status, $validStatuses, true)
    ) {

        $message = "Please enter valid risk details.";
        $messageType = "error";

    } else {

        // Save risk in the database
        $stmt = $pdo->prepare(
            "INSERT INTO risks
             (project_id, risk_title, likelihood, impact,
              response_plan, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $projectId,
            $riskTitle,
            $likelihood,
            $impact,
            $responsePlan,
            $status
        ]);

        $message = "Risk added successfully!";
    }
}


// Get all risks
$risksStmt = $pdo->query(
    "SELECT r.*, p.project_name
     FROM risks r
     INNER JOIN projects p ON p.id = r.project_id
     ORDER BY r.id DESC"
);

$risks = $risksStmt->fetchAll(PDO::FETCH_ASSOC);


// Calculate risk category
function getRiskCategory($score)
{
    if ($score <= 4) {
        return "Low";
    } elseif ($score <= 9) {
        return "Medium";
    } elseif ($score <= 15) {
        return "High";
    } else {
        return "Critical";
    }
}

// Display summary (UI only)
$riskCounts = ["Low" => 0, "Medium" => 0, "High" => 0, "Critical" => 0];
$openRisks = 0;

foreach ($risks as $r) {
    $riskCounts[getRiskCategory((int)$r["likelihood"] * (int)$r["impact"])]++;

    if ($r["status"] !== "Closed") {
        $openRisks++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Risk Management | Smart Healthcare PMS</title>

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
            font-size: 32px;
            font-weight: 850;
            color: #202b46;
            margin: 18px 0 5px;
            letter-spacing: -1px;
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

        /* Risk guide */
        .formula-box {
            background: #f6f7fd;
            border: 1px dashed #d6daf0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 700;
            color: #293650;
            margin-bottom: 16px;
        }

        .guide-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 11px 0;
            border-bottom: 1px solid #f0f2f8;
            font-size: 12px;
        }

        .guide-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .guide-range {
            color: var(--muted);
            font-weight: 700;
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

        .cell-plan {
            color: var(--muted);
            font-size: 12px;
            max-width: 280px;
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

        .score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 30px;
            padding: 0 9px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
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

        .pill-orange {
            background: #ffedd5;
            color: #c2410c;
        }

        .pill-blue {
            background: #eeedff;
            color: #4f46e5;
        }

        .score-badge.pill-green,
        .score-badge.pill-red,
        .score-badge.pill-orange,
        .score-badge.pill-blue {
            border-radius: 9px;
        }

        .score-badge::before {
            display: none;
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
                font-size: 27px;
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

            <a href="costs.php">
                <i class="bi bi-currency-rupee"></i> Cost Management
            </a>

            <a href="quality.php">
                <i class="bi bi-patch-check"></i> Quality & Defects
            </a>

            <a class="active" href="risks.php">
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
                    <h1 class="page-title">Risk Management</h1>
                    <p class="page-subtitle">
                        Identify project risks, calculate risk scores,
                        and manage response plans.
                    </p>
                </div>

                <div class="status-badge">
                    <span class="status-dot"></span>
                    <?= $openRisks ?> Active Risks
                </div>
            </div>

            <!-- Message -->
            <?php if ($message !== ""): ?>
                <div class="alert-box <?= $messageType === 'error' ? 'alert-error-soft' : 'alert-success-soft' ?>">
                    <i class="bi <?= $messageType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Risk Summary -->
            <h5 class="section-heading">Risks by Category</h5>

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Critical</h6>
                            <div class="stat-icon icon-red">
                                <i class="bi bi-exclamation-octagon"></i>
                            </div>
                        </div>
                        <h3><?= $riskCounts["Critical"] ?></h3>
                        <div class="stat-note">Score 16–25</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>High</h6>
                            <div class="stat-icon icon-orange">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                        <h3><?= $riskCounts["High"] ?></h3>
                        <div class="stat-note">Score 10–15</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Medium</h6>
                            <div class="stat-icon icon-blue">
                                <i class="bi bi-shield-exclamation"></i>
                            </div>
                        </div>
                        <h3><?= $riskCounts["Medium"] ?></h3>
                        <div class="stat-note">Score 5–9</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <h6>Low</h6>
                            <div class="stat-icon icon-green">
                                <i class="bi bi-shield-check"></i>
                            </div>
                        </div>
                        <h3><?= $riskCounts["Low"] ?></h3>
                        <div class="stat-note">Score 1–4</div>
                    </div>
                </div>

            </div>

            <?php if (count($projects) === 0): ?>

                <div class="alert-box alert-warning-soft">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Please create a project first.
                </div>

            <?php else: ?>

            <!-- Form and Guide -->
            <div class="row g-3 mb-4">

                <div class="col-lg-8">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-plus-circle text-primary me-2"></i>
                            Add Project Risk
                        </h5>

                        <p class="panel-subtitle">
                            Record a new risk with its likelihood, impact, and response plan.
                        </p>

                        <form method="POST">

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Project</label>
                                    <select name="project_id" class="form-select" required>
                                        <option value="">Select Project</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?= (int)$project["id"] ?>">
                                                <?= htmlspecialchars($project["project_name"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Risk Title</label>
                                    <input
                                        type="text"
                                        name="risk_title"
                                        class="form-control"
                                        maxlength="200"
                                        required
                                        placeholder="e.g. Appointment Scheduling Failure"
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Likelihood (1–5)</label>
                                    <select name="likelihood" class="form-select" required>
                                        <option value="">Select Likelihood</option>
                                        <option value="1">1 - Very Low</option>
                                        <option value="2">2 - Low</option>
                                        <option value="3">3 - Medium</option>
                                        <option value="4">4 - High</option>
                                        <option value="5">5 - Very High</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Impact (1–5)</label>
                                    <select name="impact" class="form-select" required>
                                        <option value="">Select Impact</option>
                                        <option value="1">1 - Very Low</option>
                                        <option value="2">2 - Low</option>
                                        <option value="3">3 - Medium</option>
                                        <option value="4">4 - High</option>
                                        <option value="5">5 - Very High</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="Open">Open</option>
                                        <option value="Monitoring">Monitoring</option>
                                        <option value="Closed">Closed</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Response Plan</label>
                                    <textarea
                                        name="response_plan"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Describe how this risk will be managed."
                                    ></textarea>
                                </div>

                            </div>

                            <button type="submit" class="btn-gradient mt-4">
                                <i class="bi bi-check2-circle"></i>
                                Save Risk
                            </button>

                        </form>

                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="panel">

                        <h5 class="panel-title">
                            <i class="bi bi-info-circle text-primary me-2"></i>
                            Risk Assessment Guide
                        </h5>

                        <p class="panel-subtitle">
                            How the risk score is calculated.
                        </p>

                        <div class="formula-box">
                            Risk Score = Likelihood × Impact
                        </div>

                        <div class="guide-row">
                            <span class="guide-range">1–4</span>
                            <span class="pill pill-green">Low Risk</span>
                        </div>

                        <div class="guide-row">
                            <span class="guide-range">5–9</span>
                            <span class="pill pill-blue">Medium Risk</span>
                        </div>

                        <div class="guide-row">
                            <span class="guide-range">10–15</span>
                            <span class="pill pill-orange">High Risk</span>
                        </div>

                        <div class="guide-row">
                            <span class="guide-range">16–25</span>
                            <span class="pill pill-red">Critical Risk</span>
                        </div>

                    </section>
                </div>

            </div>

            <?php endif; ?>

            <!-- Risk Register -->
            <section class="panel">

                <h5 class="panel-title">
                    <i class="bi bi-list-check text-primary me-2"></i>
                    Project Risk Register
                </h5>

                <p class="panel-subtitle">
                    All identified risks with their scores and response plans.
                </p>

                <div class="table-wrap">
                    <table class="pms-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Risk Title</th>
                                <th>Likelihood</th>
                                <th>Impact</th>
                                <th>Risk Score</th>
                                <th>Category</th>
                                <th>Response Plan</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($risks as $risk): ?>

                                <?php
                                $score =
                                    (int)$risk["likelihood"] *
                                    (int)$risk["impact"];

                                $category = getRiskCategory($score);

                                if ($category === "Critical") {
                                    $categoryClass = "pill-red";
                                } elseif ($category === "High") {
                                    $categoryClass = "pill-orange";
                                } elseif ($category === "Medium") {
                                    $categoryClass = "pill-blue";
                                } else {
                                    $categoryClass = "pill-green";
                                }

                                if ($risk["status"] === "Closed") {
                                    $statusClass = "pill-green";
                                } elseif ($risk["status"] === "Monitoring") {
                                    $statusClass = "pill-blue";
                                } else {
                                    $statusClass = "pill-orange";
                                }
                                ?>

                                <tr>

                                    <td class="row-id">
                                        #<?= (int)$risk["id"] ?>
                                    </td>

                                    <td>
                                        <span class="project-chip">
                                            <?= htmlspecialchars($risk["project_name"]) ?>
                                        </span>
                                    </td>

                                    <td class="cell-strong">
                                        <?= htmlspecialchars($risk["risk_title"]) ?>
                                    </td>

                                    <td><?= (int)$risk["likelihood"] ?></td>

                                    <td><?= (int)$risk["impact"] ?></td>

                                    <td>
                                        <span class="score-badge <?= $categoryClass ?>">
                                            <?= $score ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="pill <?= $categoryClass ?>">
                                            <?= htmlspecialchars($category) ?>
                                        </span>
                                    </td>

                                    <td class="cell-plan">
                                        <?= htmlspecialchars($risk["response_plan"] ?? "") ?>
                                    </td>

                                    <td>
                                        <span class="pill <?= $statusClass ?>">
                                            <?= htmlspecialchars($risk["status"]) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (count($risks) === 0): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        No risks recorded yet.
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