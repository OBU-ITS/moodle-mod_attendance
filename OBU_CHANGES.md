Oxford Brookes University | Fork Changes
========================================

## locallib.php
### attendance_renderqrcode (line: ~1390)

Include variable session id and overwrite the session ID URL parameter with the rotate QR code secret if present but not used

``` php
$hasencode = !$session->rotateqrcode && strlen($session->rotateqrcodesecret) > 0;
    $sessionid = $hasencode ? $session->rotateqrcodesecret : $session->id;

    if (strlen($session->studentpassword) > 0) {
        $qrcodeurl = $CFG->wwwroot . '/mod/attendance/attendance.php?qrpass=' .
            $session->studentpassword . '&sessid=' . $sessionid;
    } else {
        $qrcodeurl = $CFG->wwwroot . '/mod/attendance/attendance.php?sessid=' . $sessionid;
    }
```

## attendance.php
### main (line: ~32)

Update parameter type to text

``` php
$id = required_param('sessid', PARAM_TEXT);
```

## lang/en/attendance.php
### main (line ~685)

All entries below /* OBU Additional Lang */

``` php
/* OBU Additional Lang */
$string['nomatchingsessions'] = 'No attendance sessions have been found for you.';
```