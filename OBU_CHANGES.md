Oxford Brookes University | Fork Changes
========================================
----------------------------------------
# Database changes
### install.xml
```xml
<TABLE NAME="attendance_sessions" COMMENT="attendance_sessions table">
...
<FIELD NAME="roomid" TYPE="char" LENGTH="20" NOTNULL="false" SEQUENCE="false" COMMENT="Identifier for the room hosting the session"/>
<FIELD NAME="timetableeventid" TYPE="char" LENGTH="20" NOTNULL="false" SEQUENCE="false" COMMENT="Timetabling session identifier"/>
<FIELD NAME="sessioninstancecode" TYPE="char" LENGTH="62" NOTNULL="false" SEQUENCE="false" COMMENT="Encoded session instance"/>
```

### upgrade.php
```php
if ($oldversion < 2023020108) {
    $table = new xmldb_table('attendance_sessions');

    $field = new xmldb_field('roomid', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '', 'automarkcmid');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('timetableeventid', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '', 'roomid');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('sessioninstancecode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '', 'timetableeventid');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Attendance savepoint reached.
    upgrade_mod_savepoint(true, 2023020108, 'attendance');
}

if ($oldversion < 2023020111) {
    $table = new xmldb_table('attendance_sessions');
    $field = new xmldb_field('roomid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'automarkcmid');
    if ($dbman->field_exists($table, $field)) {
        $dbman->change_field_precision($table, $field);
    }

    upgrade_mod_savepoint(true, 2023020111, 'attendance');
}

if ($oldversion < 2023061401) {
    $table = new xmldb_table('attendance_sessions');
    $field = new xmldb_field('roomid', XMLDB_TYPE_CHAR, '1023', null, XMLDB_NOTNULL, null, '', 'automarkcmid');
    if ($dbman->field_exists($table, $field)) {
        $dbman->change_field_precision($table, $field);
    }

    upgrade_mod_savepoint(true, 2023061401, 'attendance');
}

if ($oldversion < 2024090402) {
    $table = new xmldb_table('attendance_sessions');
    $field = new xmldb_field('sessioninstancecode', XMLDB_TYPE_CHAR, '62', null, null, null, null, 'timetableeventid');
    if ($dbman->field_exists($table, $field)) {
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
    }
    $field = new xmldb_field('roomid', XMLDB_TYPE_CHAR, '1023', null, null, null, null, 'automarkcmid');
    if ($dbman->field_exists($table, $field)) {
        $dbman->change_field_notnull($table, $field);
    }
    $field = new xmldb_field('timetableeventid', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'roomid');
    if ($dbman->field_exists($table, $field)) {
        $dbman->change_field_notnull($table, $field);
    }

    upgrade_mod_savepoint(true, 2024090402, 'attendance');
}
```

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

### attendance_get_session_by_encoding (NEW) (line: ~1467)

``` php
/**
 * OBU Customisation
 *
 * Get attendance session by encoded session
 *
 * @param string $session_id
 * @param object $user
 */
function attendance_get_session_by_encoding($session_id, $user) {
    if(strlen($session_id) == 0 || !$user) {
        return null;
    }

    global $DB;

    $attforsessions = $DB->get_records('attendance_sessions', array('rotateqrcodesecret' => $session_id), null);

    if(!$attforsessions) {
        return $DB->get_record('attendance_sessions', array('id' => $session_id), '*', MUST_EXIST);
    }

    if(count($attforsessions) == 1) {
        return reset($attforsessions);
    }

    $sql = "SELECT s.*
            FROM {attendance_sessions} s 
            INNER JOIN {attendance} a ON a.id = s.attendanceid
            INNER JOIN {course} c ON c.id = a.course
            INNER JOIN {enrol} e ON e.courseid = c.id
            INNER JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE s.groupid = 0 AND ue.userid = :userid AND s.rotateqrcodesecret = :endcoding
            UNION
            SELECT s.*
            FROM {attendance_sessions} s 
            INNER JOIN {groups_members} m ON m.groupid = s.groupid
            WHERE s.groupid > 0 AND m.userid = :userid AND s.rotateqrcodesecret = :endcoding";

    $params = array();
    $params['userid'] = $user->id;
    $params['endcoding'] = $session_id;

    $attforsessions = $DB->get_records_sql($sql, $params);
    return reset($attforsessions);

}
```

## attendance.php
### main (line: ~32)

Update parameter type to text

Include call to new locallib function to determine the relevant attendance session

``` php
$sessid = required_param('sessid', PARAM_TEXT);
$qrpass = optional_param('qrpass', '', PARAM_TEXT);

$attforsession = attendance_get_session_by_encoding($sessid, $USER);
if (empty($attforsession)) {
    throw new moodle_exception('nomatchingsessions', 'attendance');
}

$id = $attforsession->id;
```

## classes/output/renderer.php
### construct_date_time_actions (line ~448)

Make sure deletion of sessions (with CMIS ID) can only be done by site admins

``` php 
if(strlen($sess->timetableeventid) == 0 || is_siteadmin()) {
    $url = $sessdata->url_sessions($sess->id, mod_attendance_sessions_page_params::ACTION_DELETE);
    $title = get_string('deletesession', 'attendance');
    $actions .= $this->output->action_icon($url, new pix_icon('t/delete', $title));
}
```

## password.php
### main (line ~685)

Replace the output of password.php file with the following

``` php
echo $OUTPUT->header();

$showpassword = (isset($session->studentpassword) && strlen($session->studentpassword) > 0);
$showqr = (isset($session->includeqrcode) && $session->includeqrcode == 1);
$rotateqr = (isset($session->rotateqrcode) && $session->rotateqrcode == 1);
if ($rotateqr) {
    $showpassword = false;
}
?>
    <style>
        #page {
            margin-top: 0;
            border-top: solid 4px #d10373;
        }
        #page-content {
            padding: 0 !important;
        }
        #region-main-box {
            padding: 0;
        }
        .qr-container {
            display: flex;
            flex-wrap: wrap;
            height: calc(100vh - 5px);
            max-width:1366px;
            margin: 0 auto;
        }

        .qr-left, .qr-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .qr-left {
            flex: 1 1 50%;
        }

        .qr-right {
            flex: 1 1 50%;
            text-align: center;
        }

        .qr-left img {
            max-width: calc(100% - 20px);
            max-height: calc(100vh - 20px);
            width: auto;
            height: auto;
        }

        .logo {
            max-width: 100px;
            height: auto;
            margin-bottom: 20px;
        }

        h1 {
            margin-bottom: 20px;
        }

        p {
            font-size: 1.1em;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .qr-container {
                flex-direction: column;
            }

            .logo {
                max-width: 50px;
            }

            .qr-left, .qr-right {
                flex: unset;
                width: 100%;
            }

            .qr-left img {
                max-width: calc(100% - 20px); /* 10px padding on each side */
                max-height: calc(50vh - 20px); /* 10px padding on top and bottom */
                width: auto;
                height: auto;
            }
        }
    </style>
    <div class="qr-container">
        <div class="qr-left">
            <?php
            if ($rotateqr) {
                echo html_writer::div(get_string('qrcodeheader', 'attendance'), 'qrcodeheader');
                attendance_generate_passwords($session);
                attendance_renderqrcoderotate($session);
            } else if ($showqr) {
                attendance_renderqrcode($session);
            }
            ?>
        </div>
        <div class="qr-right">
            <div>
                <img id="logoimage" src="https://moodle.brookes.ac.uk/pluginfile.php/1/core_admin/logo/0x200/1716274233/brookes_logo_dark-2x.png" class="img-fluid" alt="Brookes" />
                <?php
                if ($showpassword) {
                    if ($showqr) {
                        echo html_writer::div("<p>".get_string('qrcodeandpasswordheader', 'attendance')."</p>", 'qrcodeheader');
                    } else {
                        echo html_writer::div("<p>".get_string('passwordheader', 'attendance')."</p>", 'qrcodeheader');
                    }
                    echo html_writer::div("<h2>Password</h2>", 'student-password');
                    echo html_writer::div("<p>".$session->studentpassword."</p>", 'student-password');
                    echo html_writer::div('&nbsp;');
                }
                ?>
            </div>
        </div>
    </div>

<?php

echo $OUTPUT->footer();
```


## lang/en/attendance.php
### main (line ~685)

Update the following language strings
``` php
$string['qrcodeheader'] = 'Scan the QR code to take your attendance';
$string['qrcodeandpasswordheader'] = 'Scan the QR code or use the password listed below to take your attendance';
```

Add entries below /* OBU Additional Lang */

``` php
/* OBU Additional Lang */
$string['nomatchingsessions'] = 'No attendance sessions have been found for you.';
```
