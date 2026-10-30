<?php
require 'services/BaseService.php';
require 'controllers/BaseController.php';
require 'controllers/Api/BaseApiController.php';
require 'controllers/Api/ScheduleApiController.php';
foreach (['ClockIn', 'ClockOut', 'SaveAttendance', 'RequestAbsence', 'DecideAbsence', 'RequestSwap', 'DecideSwap', 'GetAttendance', 'GetAdjustments', 'DecideAdjustment'] as $m) {
    echo "$m: " . (method_exists(\Grocy\Controllers\Api\ScheduleApiController::class, $m) ? 'YES' : 'NO') . "\n";
}
