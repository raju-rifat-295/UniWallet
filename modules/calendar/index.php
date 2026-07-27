<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Manual Calendar Event Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $event_date  = $_POST['event_date'];
    $event_type  = $_POST['event_type'];
    $description = trim($_POST['description']);

    if (empty($title) || empty($event_date) || empty($event_type)) {
        $error = "Please fill in all required fields (Title, Date, and Type).";
    } else {
        try {
            $sql = "INSERT INTO Calender_Event (user_id, title, description, event_date, event_type, status) 
                    VALUES (:user_id, :title, :description, :event_date, :event_type, 'Upcoming')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id'     => $user_id,
                'title'       => $title,
                'description' => $description,
                'event_date'  => $event_date,
                'event_type'  => $event_type
            ]);
            $success = "Financial event scheduled successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!-- Include FullCalendar 6 CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">📅 Smart Financial Calendar</h2>
            <p class="text-muted mb-0">Google Calendar-style interface tracking bills, expected funds, loans, and savings deadlines.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge bg-light text-dark border p-2">
                <span class="badge bg-danger me-1">■</span> Bills
                <span class="badge bg-success ms-2 me-1">■</span> Income
                <span class="badge" style="background-color: #fd7e14;" class="ms-2 me-1">■</span> Loans
                <span class="badge" style="background-color: #6f42c1;" class="ms-2 me-1">■</span> Savings
            </span>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Add Manual Event Form -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold">➕ Schedule Event</h5>
            </div>
            <div class="card-body p-4">
                <form action="index.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g., Pay Wi-Fi Bill, Scholarship Date" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Date <span class="text-danger">*</span></label>
                        <input type="date" name="event_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Type <span class="text-danger">*</span></label>
                        <select name="event_type" class="form-select" required>
                            <option value="Bill" selected>🔴 Bill Payment Due</option>
                            <option value="Expected Income">🟢 Expected Incoming Funds</option>
                            <option value="General">🔵 General Reminder</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g., Keep ৳ 1200 ready in bKash"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold py-2">Add to Calendar</button>
                    </div>
                </form>

                <div class="alert alert-info border mt-4 mb-0 py-2 px-3 small">
                    💡 <strong>Smart Integration:</strong> Your active Savings Goal deadlines and pending Loan repayment dates are automatically merged into this calendar!
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Interactive FullCalendar Grid -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-3 p-3 bg-white">
            <div id="calendar" style="min-height: 600px;"></div>
        </div>
    </div>
</div>

<!-- Initialize FullCalendar via AJAX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        events: '/uniwallet/modules/calendar/fetch_events.php', // Fetches our SQL UNION JSON!
        eventClick: function(info) {
            alert('📌 ' + info.event.title + '\nType: ' + info.event.extendedProps.type + '\nStatus: ' + info.event.extendedProps.status);
        }
    });
    calendar.render();
});
</script>

<?php require_once '../../includes/footer.php'; ?>