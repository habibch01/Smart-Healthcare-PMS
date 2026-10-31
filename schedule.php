
<?php
require_once "config/database.php";

$message = "";
$messageType = "info";

$statuses = ["Not Started", "In Progress", "Completed", "Delayed"];

// Get projects
$projectsStmt = $pdo->query(
    "SELECT id, project_name FROM projects ORDER BY id DESC"
);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get team members
$membersStmt = $pdo->query(
    "SELECT id, project_id, name, role
     FROM team_members
     ORDER BY name"
);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Save task
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $projectId = (int)($_POST["project_id"] ?? 0);
    $taskName = trim($_POST["task_name"] ?? "");
    $assignedTo = (int)($_POST["assigned_to"] ?? 0);
    $duration = (int)($_POST["duration_days"] ?? 0);
    $startDate = $_POST["start_date"] ?? "";
    $endDate = $_POST["end_date"] ?? "";
    $status = $_POST["status"] ?? "Not Started";
    $dependency = (int)($_POST["dependency_task_id"] ?? 0);

    $validProject = false;

    foreach ($projects as $project) {
        if ((int)$project["id"] === $projectId) {
            $validProject = true;
            break;
        }
    }

    $validMember = ($assignedTo === 0);

    foreach ($members as $member) {
        if (
            (int)$member["id"] === $assignedTo &&
            (int)$member["project_id"] === $projectId
        ) {
            $validMember = true;
            break;
        }
    }

    $validDependency = ($dependency === 0);

    if ($dependency > 0) {
        $depStmt = $pdo->prepare(
            "SELECT id, start_date FROM tasks
             WHERE id = ? AND project_id = ?"
        );
        $depStmt->execute([$dependency, $projectId]);
        $depTask = $depStmt->fetch(PDO::FETCH_ASSOC);

        if ($depTask) {
            $validDependency = true;

            if ($dependency > 0 && $depTask["start_date"] > $startDate) {
                $validDependency = false;
                $message = "Dependency task cannot start after this task.";
            }
        }
    }

    $validDates = (
        $startDate !== "" &&
        $endDate !== "" &&
        $endDate >= $startDate &&
        strtotime($startDate) !== false &&
        strtotime($endDate) !== false
    );

    if (
        !$validProject ||
        $taskName === "" ||
        strlen($taskName) > 200 ||
        $duration < 1 ||
        !$validDates ||
        !$validMember ||
        !$validDependency ||
        !in_array($status, $statuses, true)
    ) {
        if ($message === "") {
            $message = "Please enter valid task details.";
        }
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO tasks
                 (project_id, task_name, assigned_to, duration_days,
                  start_date, end_date, status, dependency_task_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $projectId,
                $taskName,
                $assignedTo === 0 ? null : $assignedTo,
                $duration,
                $startDate,
                $endDate,
                $status,
                $dependency === 0 ? null : $dependency
            ]);

            $message = "Task saved successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Task could not be saved. Please check your database fields.";
            $messageType = "danger";
        }
    }
}

// Get tasks
$tasksStmt = $pdo->query(
    "SELECT t.*, p.project_name, tm.name AS member_name,
            d.task_name AS dependency_name
     FROM tasks t
     INNER JOIN projects p ON t.project_id = p.id
     LEFT JOIN team_members tm ON t.assigned_to = tm.id
     LEFT JOIN tasks d ON t.dependency_task_id = d.id
     ORDER BY t.start_date ASC, t.id ASC"
);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$chartTasks = [];

foreach ($tasks as $task) {
    if (
        empty($task["start_date"]) ||
        empty($task["end_date"]) ||
        strtotime($task["start_date"]) === false ||
        strtotime($task["end_date"]) === false
    ) {
        continue;
    }

    $start = new DateTime($task["start_date"]);
    $end = new DateTime($task["end_date"]);

    if ($end < $start) {
        continue;
    }

    $days = (int)$start->diff($end)->days + 1;
    $duration = max(1, (int)$task["duration_days"]);

    $chartTasks[] = [
        "id" => (int)$task["id"],
        "project_id" => (int)$task["project_id"],
        "project" => $task["project_name"],
        "name" => $task["task_name"],
        "member" => $task["member_name"] ?? "Not Assigned",
        "start" => $task["start_date"],
        "end" => $task["end_date"],
        "days" => $days,
        "duration" => $duration,
        "status" => $task["status"]
    ];
}

// Calculate overall progress
$totalTasks = count($tasks);
$completedTasks = 0;

foreach ($tasks as $task) {
    if ($task["status"] === "Completed") {
        $completedTasks++;
    }
}

$progress = $totalTasks > 0
    ? round(($completedTasks / $totalTasks) * 100)
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Schedule & Gantt | Smart Healthcare PMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --purple: #8b5cf6;
            --navy: #17213a;
            --muted: #8490a5;
            --bg: #f5f7ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top right, #e9eaff, transparent 28%),
                var(--bg);
            color: var(--navy);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .main {
            max-width: 1500px;
            padding-top: 32px;
            padding-bottom: 40px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 29px;
            font-weight: 850;
            letter-spacing: -0.8px;
            margin-bottom: 7px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .btn-back {
            border: 1px solid #e1e6f0;
            background: white;
            color: #34415c;
            border-radius: 10px;
            padding: 10px 15px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            white-space: nowrap;
        }

        .btn-back:hover {
            background: #eef0ff;
            color: #4f46e5;
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 26px;
            border-radius: 20px;
            color: white;
            background: linear-gradient(115deg, #312e81, #6366f1 65%, #8b5cf6);
            box-shadow: 0 12px 30px rgba(99, 102, 241, .18);
            margin-bottom: 24px;
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -40px;
            top: -95px;
            width: 230px;
            height: 230px;
            border-radius: 50%;
            background: rgba(255,255,255,.09);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-label {
            color: #e0e7ff;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .hero h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 9px;
        }

        .hero p {
            color: #e0e7ff;
            font-size: 13px;
            margin: 0;
        }

        .stat-card, .panel {
            background: rgba(255,255,255,.95);
            border: 1px solid #e9edf6;
            border-radius: 17px;
            box-shadow: 0 6px 24px rgba(31,41,55,.04);
        }

        .stat-card {
            padding: 20px;
            height: 100%;
            transition: .25s ease;
        }

        .stat-card:hover, .panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(31,41,55,.075);
        }

        .stat-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stat-number {
            font-size: 29px;
            font-weight: 850;
            margin-top: 12px;
            margin-bottom: 0;
        }

        .stat-icon {
            float: right;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 19px;
            background: #eeedff;
            color: #6366f1;
        }

        .panel {
            padding: 24px;
            margin-top: 24px;
            min-width: 0;
            transition: .25s ease;
        }

        .panel-heading {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .panel-description {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }

        .form-control, .form-select {
            border: 1px solid #e0e6f0;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 13px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }

        .btn-primary {
            background: linear-gradient(110deg, #6366f1, #8b5cf6);
            border: 0;
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 700;
        }

        .btn-primary:hover {
            background: linear-gradient(110deg, #4f46e5, #7c3aed);
        }

        .section-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .filter-select {
            max-width: 280px;
            min-width: 200px;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 18px;
            color: #64748b;
            font-size: 11px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-color {
            width: 10px;
            height: 10px;
            border-radius: 3px;
        }

        .gantt-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid #e9edf6;
            border-radius: 12px;
            background: #fff;
        }

        .gantt {
            min-width: 850px;
            font-size: 11px;
        }

        .gantt-header, .gantt-row {
            display: grid;
            grid-template-columns: 230px minmax(600px, 1fr);
        }

        .gantt-header {
            background: #f8f9ff;
            border-bottom: 1px solid #e9edf6;
            font-weight: 750;
            color: #64748b;
        }

        .gantt-task-heading {
            padding: 14px;
            border-right: 1px solid #e9edf6;
        }

        .timeline-header {
            position: relative;
            display: flex;
            min-height: 43px;
            align-items: stretch;
        }

        .month-cell {
            flex: 1;
            min-width: 0;
            text-align: center;
            padding: 14px 2px;
            border-right: 1px solid #e9edf6;
            white-space: nowrap;
        }

        .gantt-row {
            min-height: 55px;
            border-bottom: 1px solid #f0f2f8;
        }

        .gantt-row:last-child {
            border-bottom: 0;
        }

        .gantt-task {
            padding: 10px 13px;
            border-right: 1px solid #e9edf6;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            overflow: hidden;
        }

        .task-name {
            font-size: 12px;
            font-weight: 750;
            color: #293650;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .task-date {
            color: #94a3b8;
            font-size: 10px;
        }

        .timeline {
            position: relative;
            display: grid;
            align-items: center;
            background-image: linear-gradient(to right, #edf0f7 1px, transparent 1px);
            background-size: calc(100% / var(--days)) 100%;
            min-height: 55px;
        }

        .gantt-bar {
            position: absolute;
            height: 23px;
            border-radius: 7px;
            min-width: 4px;
            box-shadow: 0 2px 6px rgba(15,23,42,.1);
            transition: filter .2s ease;
        }

        .gantt-bar:hover {
            filter: brightness(.94);
        }

        .status-not-started {
            background: #94a3b8;
        }

        .status-in-progress {
            background: linear-gradient(90deg, #3b82f6, #6366f1);
        }

        .status-completed {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .status-delayed {
            background: linear-gradient(90deg, #f97316, #ef4444);
        }

        .gantt-empty {
            padding: 35px 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }

        .table {
            margin-bottom: 0;
            font-size: 12px;
            vertical-align: middle;
        }

        .table thead th {
            background: #f8f9ff;
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 13px;
            white-space: nowrap;
            border-bottom: 1px solid #e8edf5;
        }

        .table tbody td {
            padding: 13px;
            color: #475569;
        }

        .task-title {
            color: #26334d;
            font-weight: 750;
        }

        .status-pill {
            display: inline-block;
            border-radius: 30px;
            padding: 6px 9px;
            font-size: 10px;
            font-weight: 750;
            white-space: nowrap;
        }

        .pill-not-started {
            background: #f1f5f9;
            color: #64748b;
        }

        .pill-in-progress {
            background: #e0e7ff;
            color: #4338ca;
        }

        .pill-completed {
            background: #d1fae5;
            color: #047857;
        }

        .pill-delayed {
            background: #ffedd5;
            color: #c2410c;
        }

        .progress {
            height: 9px;
            border-radius: 20px;
            background: #eceeff;
        }

        .progress-bar {
            background: linear-gradient(90deg, #6366f1, #a855f7);
        }

        .footer {
            text-align: center;
            color: #9aa4b6;
            font-size: 11px;
            padding: 28px 0 0;
        }

        @media (max-width: 767px) {
            .main {
                padding-top: 20px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-title {
                font-size: 24px;
            }

            .hero {
                padding: 22px;
            }

            .hero h2 {
                font-size: 20px;
            }

            .panel {
                padding: 17px;
            }

            .filter-select {
                max-width: 100%;
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="container-fluid main">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="bi bi-calendar3 text-primary"></i>
                Schedule & Gantt
            </h1>
            <p class="subtitle">
                Plan, schedule and visualize project tasks on a timeline.
            </p>
        </div>

        <a href="dashboard.php" class="btn-back">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Banner -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-label">
                <i class="bi bi-stars"></i> Project Planning Workspace
            </div>
            <h2>Schedule & Gantt Management</h2>
            <p>
                Manage task dates, team assignments and dependencies,
                then view your complete project timeline in one place.
            </p>
        </div>
    </section>

    <!-- Summary cards -->
    <div class="row g-3 mb-1">
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon">
                    <i class="bi bi-list-task"></i>
                </span>
                <div class="stat-label">Total Tasks</div>
                <h3 class="stat-number"><?= $totalTasks ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon" style="background:#d1fae5;color:#059669;">
                    <i class="bi bi-check2-circle"></i>
                </span>
                <div class="stat-label">Completed Tasks</div>
                <h3 class="stat-number"><?= $completedTasks ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon" style="background:#e0e7ff;color:#4f46e5;">
                    <i class="bi bi-graph-up-arrow"></i>
                </span>
                <div class="stat-label">Overall Progress</div>
                <h3 class="stat-number"><?= $progress ?>%</h3>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon" style="background:#ffedd5;color:#ea580c;">
                    <i class="bi bi-people"></i>
                </span>
                <div class="stat-label">Team Members</div>
                <h3 class="stat-number"><?= count($members) ?></h3>
            </div>
        </div>
    </div>

    <!-- Add Task -->
    <section class="panel">
        <h5 class="panel-heading">
            <i class="bi bi-plus-circle text-primary me-2"></i>
            Add Project Task
        </h5>
        <p class="panel-description">
            Enter the task details to add it to your schedule and Gantt chart.
        </p>

        <?php if (count($projects) === 0): ?>
            <div class="alert alert-warning mb-0">
                Please create a project first before adding tasks.
            </div>
        <?php else: ?>

        <form method="POST">
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Project</label>
                    <select name="project_id" id="project_id" class="form-select" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int)$project["id"] ?>">
                                <?= htmlspecialchars($project["project_name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Task Name</label>
                    <input type="text" name="task_name" class="form-control"
                           maxlength="200" required
                           placeholder="e.g. Database Design">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assign Team Member</label>
                    <select name="assigned_to" id="assigned_to" class="form-select">
                        <option value="0">Not Assigned</option>
                        <?php foreach ($members as $member): ?>
                            <option
                                value="<?= (int)$member["id"] ?>"
                                data-project="<?= (int)$member["project_id"] ?>">
                                <?= htmlspecialchars($member["name"] . " - " . $member["role"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Depends On (Optional)</label>
                    <select name="dependency_task_id" id="dependency_task_id" class="form-select">
                        <option value="0">No Dependency</option>
                        <?php foreach ($tasks as $task): ?>
                            <option
                                value="<?= (int)$task["id"] ?>"
                                data-project="<?= (int)$task["project_id"] ?>">
                                <?= htmlspecialchars($task["project_name"] . " - " . $task["task_name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Duration (Days)</label>
                    <input type="number" name="duration_days" id="duration_days"
                           class="form-control" min="1" required
                           placeholder="e.g. 5">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date"
                           class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date"
                           class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Task Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Not Started">Not Started</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Delayed">Delayed</option>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Save Task
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <!-- Gantt Chart -->
    <section class="panel">
        <div class="section-toolbar">
            <div>
                <h5 class="panel-heading mb-1">
                    <i class="bi bi-bar-chart-steps text-primary me-2"></i>
                    Gantt Chart
                </h5>
                <p class="panel-description mb-0">
                    Visual timeline of scheduled tasks and their durations.
                </p>
            </div>

            <select id="ganttProjectFilter" class="form-select filter-select">
                <option value="all">All Projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int)$project["id"] ?>">
                        <?= htmlspecialchars($project["project_name"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="legend">
            <span class="legend-item">
                <span class="legend-color" style="background:#94a3b8;"></span>
                Not Started
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background:#6366f1;"></span>
                In Progress
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background:#10b981;"></span>
                Completed
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background:#f97316;"></span>
                Delayed
            </span>
        </div>

        <div id="ganttContainer" class="gantt-scroll">
            <div class="gantt-empty">Loading Gantt Chart...</div>
        </div>
    </section>

    <!-- Scheduled Tasks -->
    <section class="panel">
        <div class="section-toolbar">
            <div>
                <h5 class="panel-heading mb-1">
                    <i class="bi bi-table text-primary me-2"></i>
                    Scheduled Tasks
                </h5>
                <p class="panel-description mb-0">
                    Detailed list of task dates, assignments and dependencies.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th>Assigned To</th>
                        <th>Duration</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Dependency</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $statusClass = match ($task["status"]) {
                        "In Progress" => "pill-in-progress",
                        "Completed" => "pill-completed",
                        "Delayed" => "pill-delayed",
                        default => "pill-not-started"
                    };
                    ?>
                    <tr>
                        <td><?= (int)$task["id"] ?></td>
                        <td><?= htmlspecialchars($task["project_name"]) ?></td>
                        <td class="task-title"><?= htmlspecialchars($task["task_name"]) ?></td>
                        <td><?= htmlspecialchars($task["member_name"] ?? "Not Assigned") ?></td>
                        <td><?= (int)$task["duration_days"] ?> days</td>
                        <td><?= htmlspecialchars($task["start_date"]) ?></td>
                        <td><?= htmlspecialchars($task["end_date"]) ?></td>
                        <td><?= htmlspecialchars($task["dependency_name"] ?? "None") ?></td>
                        <td>
                            <span class="status-pill <?= $statusClass ?>">
                                <?= htmlspecialchars($task["status"]) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($tasks) === 0): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">
                            No tasks added yet. Add your first task above.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer">
        Smart Healthcare PMS &nbsp;•&nbsp; Schedule & Gantt Management
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const ganttTasks = <?= json_encode(
    $chartTasks,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const ganttContainer = document.getElementById("ganttContainer");
const projectFilter = document.getElementById("ganttProjectFilter");

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, function(char) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[char];
    });
}

function formatDate(dateString) {
    const date = new Date(dateString + "T12:00:00");
    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });
}

function getStatusClass(status) {
    switch (status) {
        case "In Progress":
            return "status-in-progress";
        case "Completed":
            return "status-completed";
        case "Delayed":
            return "status-delayed";
        default:
            return "status-not-started";
    }
}

function renderGantt() {
    const selectedProject = projectFilter.value;

    const filteredTasks = ganttTasks.filter(task => {
        return selectedProject === "all" ||
            String(task.project_id) === selectedProject;
    });

    if (filteredTasks.length === 0) {
        ganttContainer.innerHTML =
            '<div class="gantt-empty">' +
            'No scheduled tasks found for this project. Add tasks with valid dates to display the Gantt Chart.' +
            '</div>';
        return;
    }

    const timestamps = filteredTasks.flatMap(task => [
        Date.parse(task.start + "T00:00:00Z"),
        Date.parse(task.end + "T00:00:00Z")
    ]).filter(Number.isFinite);

    if (timestamps.length === 0) {
        ganttContainer.innerHTML =
            '<div class="gantt-empty">No valid task dates available.</div>';
        return;
    }

    const dayMs = 86400000;
    const earliest = Math.min(...timestamps);
    const latest = Math.max(...timestamps);

    // Add a small margin before and after the schedule.
    const firstDay = earliest - dayMs * 2;
    const lastDay = latest + dayMs * 2;
    const totalDays = Math.max(5, Math.round((lastDay - firstDay) / dayMs) + 1);

    const monthHeaders = [];
    let cursor = new Date(firstDay);

    while (cursor.getTime() <= lastDay) {
        const year = cursor.getUTCFullYear();
        const month = cursor.getUTCMonth();

        const monthStart = Date.UTC(year, month, 1);
        const nextMonth = Date.UTC(year, month + 1, 1);
        const visibleStart = Math.max(firstDay, monthStart);
        const visibleEnd = Math.min(lastDay + dayMs, nextMonth);

        const daysInSegment = Math.max(
            1,
            Math.round((visibleEnd - visibleStart) / dayMs)
        );

        monthHeaders.push({
            label: cursor.toLocaleDateString("en-US", {
                month: "short",
                year: "numeric",
                timeZone: "UTC"
            }),
            days: daysInSegment
        });

        cursor = new Date(nextMonth);
    }

    let headerHTML = `
        <div class="gantt-header">
            <div class="gantt-task-heading">Task / Start Date</div>
            <div class="timeline-header">
    `;

    monthHeaders.forEach(month => {
        headerHTML += `
            <div class="month-cell"
                 style="flex:${month.days} 0 0;">
                ${escapeHTML(month.label)}
            </div>
        `;
    });

    headerHTML += `</div></div>`;

    let rowsHTML = "";

    filteredTasks.forEach(task => {
        const taskStart = Date.parse(task.start + "T00:00:00Z");
        const taskEnd = Date.parse(task.end + "T00:00:00Z");

        if (!Number.isFinite(taskStart) || !Number.isFinite(taskEnd)) {
            return;
        }

        const leftDays = (taskStart - firstDay) / dayMs;
        const durationDays = Math.max(
            1,
            (taskEnd - taskStart) / dayMs + 1
        );

        const leftPercent = (leftDays / totalDays) * 100;
        const widthPercent = (durationDays / totalDays) * 100;

        const statusClass = getStatusClass(task.status);

        const tooltip =
            task.name + " | " +
            task.start + " to " + task.end + " | " +
            task.status;

        rowsHTML += `
            <div class="gantt-row">
                <div class="gantt-task">
                    <div class="task-name" title="${escapeHTML(task.name)}">
                        ${escapeHTML(task.name)}
                    </div>
                    <div class="task-date">
                        ${escapeHTML(formatDate(task.start))}
                        · ${escapeHTML(task.duration)} days
                    </div>
                </div>

                <div class="timeline" style="--days:${totalDays};">
                    <div
                        class="gantt-bar ${statusClass}"
                        style="left:${leftPercent}%;width:${widthPercent}%;"
                        title="${escapeHTML(tooltip)}"
                        aria-label="${escapeHTML(tooltip)}">
                    </div>
                </div>
            </div>
        `;
    });

    ganttContainer.innerHTML = `
        <div class="gantt">
            ${headerHTML}
            ${rowsHTML}
        </div>
    `;
}

projectFilter.addEventListener("change", renderGantt);
renderGantt();

// Automatically calculate end date from start date + duration.
// The duration is inclusive: a 1-day task ends on its start date.
const startInput = document.getElementById("start_date");
const endInput = document.getElementById("end_date");
const durationInput = document.getElementById("duration_days");

function updateEndDate() {
    if (!startInput || !endInput || !durationInput) return;

    const start = startInput.value;
    const duration = parseInt(durationInput.value, 10);

    if (!start || !Number.isFinite(duration) || duration < 1) return;

    const date = new Date(start + "T12:00:00");
    date.setDate(date.getDate() + duration - 1);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    endInput.value = `${year}-${month}-${day}`;
}

if (startInput && durationInput && endInput) {
    startInput.addEventListener("change", updateEndDate);
    durationInput.addEventListener("input", updateEndDate);
}

// Filter assignments and dependencies by selected project.
const projectInput = document.getElementById("project_id");
const memberInput = document.getElementById("assigned_to");
const dependencyInput = document.getElementById("dependency_task_id");

function filterProjectOptions(selectElement, projectId) {
    if (!selectElement) return;

    Array.from(selectElement.options).forEach(option => {
        if (option.value === "0") {
            option.hidden = false;
            return;
        }

        option.hidden = option.dataset.project !== String(projectId);
    });

    if (selectElement.selectedOptions[0]?.hidden) {
        selectElement.value = "0";
    }
}

function updateProjectOptions() {
    if (!projectInput) return;

    filterProjectOptions(memberInput, projectInput.value);
    filterProjectOptions(dependencyInput, projectInput.value);
}

if (projectInput) {
    projectInput.addEventListener("change", updateProjectOptions);
    updateProjectOptions();
}
</script>

</body>
</html>
```