<?php

include 'connection.php';

header('Content-Type: application/json');

$current_date = date('Y-m-d');
$current_time = date('H:i:s');


/*
|--------------------------------------------------------------------------
| MARK STUDENTS ABSENT AFTER TIME-OUT DEADLINE
|--------------------------------------------------------------------------
|
| If:
| - Student has Time-In
| - Student has NO Time-Out
| - Current time is already past section end_time
|
| Then:
| status = Absent
|
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE attendance a

    INNER JOIN student s
        ON a.student_id = s.id

    INNER JOIN section sec
        ON s.section = sec.section_name

    SET a.status = 'Absent'

    WHERE a.date = '$current_date'

      AND a.time_in IS NOT NULL

      AND (
            a.time_out IS NULL
            OR a.time_out = ''
          )

      AND '$current_time' > sec.end_time

      AND a.status <> 'Absent'
";


$result = mysqli_query($conn, $sql);


if ($result) {

    echo json_encode([
        'status' => 'success',
        'updated' => mysqli_affected_rows($conn),
        'time' => $current_time
    ]);

} else {

    echo json_encode([
        'status' => 'error',
        'message' => mysqli_error($conn)
    ]);
}

?>