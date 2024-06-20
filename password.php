<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Displays help via AJAX call or in a new page
 *
 * Use {@see core_renderer::help_icon()} or {@see addHelpButton()} to display
 * the help icon.
 *
 * @copyright  2017 Dan Marsden
 * @package    mod_attendance
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir.'/tcpdf/tcpdf_barcodes_2d.php'); // Used for generating qrcode.
require_once($CFG->libdir.'/outputrenderers.php');

$session = required_param('session', PARAM_INT);
$session = $DB->get_record('attendance_sessions', array('id' => $session), '*', MUST_EXIST);

$cm = get_coursemodule_from_instance('attendance', $session->attendanceid);
require_login($cm->course, $cm);

$context = context_module::instance($cm->id);
$capabilities = array('mod/attendance:manageattendances', 'mod/attendance:takeattendances', 'mod/attendance:changeattendances');
if (!has_any_capability($capabilities, $context)) {
    exit;
}

if (optional_param('returnpasswords', 0, PARAM_INT) == 1) {
    header('Content-Type: application/json');
    echo attendance_return_passwords($session);
    exit;
}

$PAGE->set_url('/mod/attendance/password.php');
$PAGE->set_pagelayout('popup');

$PAGE->set_context(context_system::instance());

$PAGE->set_title(get_string('password', 'attendance'));

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
