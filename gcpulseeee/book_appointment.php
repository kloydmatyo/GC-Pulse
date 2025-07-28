<form action="submit_appointment.php" method="POST">
    <input type="text" name="title" placeholder="Appointment Title" required>
    <textarea name="description" placeholder="Describe the purpose of the appointment"></textarea>

    <label for="appointment_date">Select Date & Time:</label>
    <input type="datetime-local" name="appointment_date" required>

    <button type="submit">Request Appointment</button>
</form>
