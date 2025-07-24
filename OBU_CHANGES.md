Oxford Brookes University | Fork Changes
========================================
----------------------------------------
# Database changes
### install.xml
```xml
<TABLE NAME="attendance_sessions" COMMENT="attendance_sessions table">
...
<FIELD NAME="roomid" TYPE="char" LENGTH="1023" NOTNULL="false" SEQUENCE="false" COMMENT="Identifier for the room hosting the session"/>
<FIELD NAME="timetableeventid" TYPE="char" LENGTH="20" NOTNULL="false" SEQUENCE="false" COMMENT="Timetabling session identifier"/>
<FIELD NAME="sessioninstancecode" TYPE="char" LENGTH="62" NOTNULL="false" SEQUENCE="false" COMMENT="Encoded session instance"/>
```

## locallib.php
### attendance_renderqrcode (line: ~1390)

Include variable session id and overwrite the session ID URL parameter with the rotate QR code secret if present but not used

``` php
    $sessionid = strlen($session->sessioninstancecode) > 0
        ? $session->sessioninstancecode
        : $session->id;

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
function attendance_get_session_by_encoding($session_id, $user)
{
    if (strlen($session_id) == 0 || !$user) {
        return null;
    }

    global $DB;

    $attforsessions = $DB->get_records('attendance_sessions', array('sessioninstancecode' => $session_id), null);

    if (!$attforsessions) {
        return $DB->get_record('attendance_sessions', array('id' => $session_id), '*', MUST_EXIST);
    }

    if (count($attforsessions) == 1) {
        return reset($attforsessions);
    }

    $params = array();
    $params['userid'] = $user->id;
    $params['endcoding'] = $session_id;

    $sql = "SELECT s.*
            FROM {attendance_sessions} s 
            INNER JOIN {groups_members} m ON m.groupid = s.groupid
            WHERE s.groupid > 0 AND m.userid = :userid AND s.sessioninstancecode = :endcoding";

    $attforsessionsfiltered = $DB->get_records_sql($sql, $params);

    if (count($attforsessionsfiltered) > 0) {
        return reset($attforsessionsfiltered);
    }

    $sql = "SELECT s.*
            FROM {attendance_sessions} s 
            INNER JOIN {attendance} a ON a.id = s.attendanceid
            INNER JOIN {course} c ON c.id = a.course
            INNER JOIN {enrol} e ON e.courseid = c.id
            INNER JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :userid AND s.sessioninstancecode = :endcoding";

    $attforsessionsfiltered = $DB->get_records_sql($sql, $params);

    if (count($attforsessionsfiltered) > 0) {
        return reset($attforsessionsfiltered);
    }
    else {
        return reset($attforsessions);
    }
}
```

## attendance.php
### Line: ~32

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
### Line: ~49
Require user is logged in but not required to be enrolled on course or in group
``` php
//require_login($course, true, $cm);
require_login();

// If group mode is set, check if user can access this session.
//if (!empty($attforsession->groupid) && !groups_is_member($attforsession->groupid, $USER->id)) {
//    throw new moodle_exception('cannottakethisgroup', 'attendance');
//}
```
### Line: ~231
Alter the redirect location to Moodle home in case a student is not enrolled on the course
``` php
// $url = new moodle_url('/mod/attendance/view.php', ['id' => $cm->id]);
$url = new moodle_url('/');
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


## pix/monologo.svg
Update monologo.svg to the following:

```svg
<?xml version="1.0" encoding="utf-8"?>
<!-- Generator: Adobe Illustrator 28.3.0, SVG Export Plug-In . SVG Version: 6.00 Build 0)  -->
<svg version="1.1" id="Layer_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
	 viewBox="0 0 2000 2000" style="enable-background:new 0 0 2000 2000;" xml:space="preserve">
<path d="M1004,873.8c-4.4,0.4-8.8,0.6-13.3,0.6c-41.5,0-80.5-15.9-109.9-44.8c-14.1-13.9-25.2-29.9-32.9-47.6
	c-7.6-17.4-11.5-35.8-11.8-54.6c1.3-24.8,6.7-47.1,15.8-66.5c8.2-17.3,19.5-32.3,33.6-44.6c13.6-11.8,29.6-21,47.5-27.4
	c17.9-6.3,36.9-9.5,56.5-9.5c19.5,0,38.5,3.2,56.3,9.5c17.8,6.3,33.7,15.5,47.1,27.2c14,12.2,25.1,27.1,33.1,44.3
	c8.9,19.1,14,41.2,15.3,65.7c24.1-11.8,49.7-20.2,76-25.3c-16.6-133-122.1-199.5-227.8-199.5c-112.1,0-224.5,74.8-231.4,224.3
	c0.1,121,100.1,223.1,225.9,226.6C987.7,923.7,994.5,897.5,1004,873.8z"/>
<path d="M705.2,632.7c-4.4,0.4-8.8,0.6-13.3,0.6c-41.5,0-80.5-15.9-109.9-44.8c-14.1-13.9-25.2-29.9-32.9-47.6
	c-7.6-17.4-11.5-35.8-11.8-54.6c1.3-24.8,6.7-47.1,15.8-66.5c8.2-17.3,19.5-32.3,33.6-44.6c13.6-11.8,29.6-21,47.5-27.4
	c17.9-6.3,36.9-9.5,56.5-9.5c19.5,0,38.5,3.2,56.3,9.5c17.8,6.3,33.7,15.5,47.1,27.2c14,12.2,25.1,27.1,33.1,44.3
	c8.9,19.1,14,41.2,15.3,65.7c24.1-11.8,49.7-20.2,76-25.3c-16.6-133-122.1-199.5-227.8-199.5c-112.1,0-224.5,74.8-231.4,224.3
	c0.1,121,100.1,223.1,225.9,226.6C688.9,682.6,695.8,656.5,705.2,632.7z"/>
<g>
	<path d="M1320.5,1355c87.2,3.7,170.2,29.6,240.2,75.2c32.4,21.3,57.1,46.1,73.4,73.7c14.8,25,22.4,52,22.4,80v58.7H913.6v-58.8
		c0-28,7.5-54.9,22.3-80c16.3-27.5,41-52.3,73.3-73.6c69.9-45.6,152.9-71.5,240.2-75.2h25.7h7.8h2h2h7.8H1320.5 M1322.1,1277
		c-9.8,0-17.5,0-27.3,0c-2,0-5.9,0-7.8,0h-2h-2c-2,0-5.9,0-7.8,0c-9.8,0-17.5,0-27.3,0c-99.7,3.9-197.4,33.2-281.4,87.9
		c-85.9,56.6-130.9,134.8-130.9,218.8v82.1c0,29.3,21.5,52.7,52.7,54.7h793.4c31.3-2,52.7-25.4,52.7-54.7v-82.1
		c-0.1-84.1-45.1-162.2-131.1-218.8C1519.4,1310.2,1421.7,1280.8,1322.1,1277L1322.1,1277z"/>
</g>
<g>
	<path d="M1278.5,837.3c19.5,0,38.5,3.2,56.3,9.5c17.8,6.3,33.7,15.5,47.1,27.2c14,12.2,25.1,27.1,33.1,44.3
		c9,19.3,14.2,41.8,15.3,66.7c-0.8,82.7-66.7,147.2-150.6,147.2c-41.5,0-80.5-15.9-109.9-44.8c-14.1-13.9-25.2-29.9-32.9-47.6
		c-7.6-17.4-11.5-35.8-11.8-54.6c1.3-24.8,6.7-47.1,15.8-66.5c8.2-17.3,19.5-32.3,33.6-44.6c13.6-11.8,29.6-21,47.5-27.4
		C1240,840.6,1259,837.3,1278.5,837.3 M1278.5,759.3c-112.1,0-224.5,74.8-231.4,224.3c0.1,123.1,103.7,226.7,232.6,226.7
		c127,0,228.6-99.7,228.6-226.7C1502.5,834.1,1390.7,759.3,1278.5,759.3L1278.5,759.3z"/>
</g>
<path d="M987.5,1009.8c-9.1,0-16.6,0-25.8,0c-99.7,3.9-197.4,33.2-281.3,87.9c-85.9,56.6-130.9,134.8-130.9,218.8v82.1
	c0,29.3,21.5,52.7,52.7,54.7H804c3.7-7.6,7.8-15.1,12.1-22.5c11.6-19.6,25.4-38.1,41.2-55.5h-230v-58.8c0-28,7.5-54.9,22.3-80
	c16.3-27.5,40.9-52.3,73.3-73.6c69.9-45.6,152.9-71.5,240.2-75.2h25.7h7.8h2h2h5.4C996.4,1063.1,990,1037,987.5,1009.8z"/>
<path d="M698.8,779.1c-9.1,0-16.6,0-25.8,0c-99.7,3.9-197.4,33.2-281.3,87.9c-85.9,56.6-130.9,134.8-130.9,218.8v82.1
	c0,29.3,21.5,52.7,52.7,54.7h201.9c3.7-7.6,7.8-15.1,12.1-22.5c11.6-19.6,25.4-38.1,41.2-55.5h-230v-58.8c0-28,7.5-54.9,22.3-80
	c16.3-27.5,40.9-52.3,73.3-73.6c69.9-45.6,152.9-71.5,240.2-75.2h25.7h7.8h2h2h5.4C707.7,832.4,701.3,806.2,698.8,779.1z"/>
<path d="M1372.9,1238.9c26.1,2.7,51.8,7.1,76.9,13c-4.6-13.9-10.5-27.5-17.7-40.6C1413.8,1222.7,1394,1232,1372.9,1238.9z"/>
</svg>
```