<?php
#**************************************************************************
#  openSIS is a free student information system for public and non-public 
#  schools from Open Solutions for Education, Inc. web: www.os4ed.com
#
#  openSIS is  web-based, open source, and comes packed with features that 
#  include student demographic info, scheduling, grade book, attendance, 
#  report cards, eligibility, transcripts, parent portal, 
#  student portal and more.   
#
#  Visit the openSIS web site at http://www.opensis.com to learn more.
#  If you have question regarding this system or the license, please send 
#  an email to info@os4ed.com.
#
#  This program is released under the terms of the GNU General Public License as  
#  published by the Free Software Foundation, version 2 of the License. 
#  See license.txt.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#***************************************************************************************
error_reporting(0);
session_start();
include("functions/ParamLibFnc.php");
include("Data.php");
include("functions/DbGetFnc.php");
include("functions/MonthNwSwitchFnc.php");
include("functions/ProperDateFnc.php");
require_once("functions/PragRepFnc.php");

function DateInputAY($value, $name, $counter = 1, $placeholder = "Enter Date") {

    $show = "";
    $date_sep = "";
    $monthVal = "";
    $yearVal = "";
    $dayVal = "";
    $display = "";

    if ($value != '')
        return '<table><tr><td><div id="date_div_' . $counter . '" style="display: inline" >' . ProperDateAY($value) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div></td><td><input type=text id="date_' . $counter . '" ' . $show . '  style="display:none" readonly></td><td><a onClick="init(' . $counter . ',2);"><img src="assets/calendar.gif"  /></a></td><td><input type=hidden ' . $monthVal . ' id="monthSelect' . $counter . '" name="month_' . $name . '" ><input type=hidden ' . $dayVal . '  id="daySelect' . $counter . '"   name="day_' . $name . '"><input type=hidden ' . $yearVal . '  id="yearSelect' . $counter . '" name="year_' . $name . '" ></td></tr></table>';
    else {
        if ($counter == 2)
            return '<input type="text" id="date_' . $counter . '" required data-placeholder="' . $placeholder . '" class="form-control daterange-single"><input type=hidden ' . $monthVal . ' id="monthSelect' . $counter . '" name="month_' . $name . '" disabled=disabled><input type=hidden ' . $dayVal . '  id="daySelect' . $counter . '"   name="day_' . $name . '" disabled=disabled><input type=hidden ' . $yearVal . '  id="yearSelect' . $counter . '" name="year_' . $name . '" disabled=disabled>';
        else
            return '<input type="text" id="date_' . $counter . '" required data-placeholder="' . $placeholder . '" class="form-control daterange-single"><input type=hidden ' . $monthVal . ' id="monthSelect' . $counter . '" name="month_' . $name . '" ><input type=hidden ' . $dayVal . '  id="daySelect' . $counter . '"   name="day_' . $name . '"><input type=hidden ' . $yearVal . '  id="yearSelect' . $counter . '" name="year_' . $name . '" >';
    }
}

function db_start() {
    global $DatabaseServer, $DatabaseUsername, $DatabasePassword, $DatabaseName, $DatabasePort, $DatabaseType;

    switch ($DatabaseType) {
        case 'mysqli':
            $connection = new mysqli($DatabaseServer, $DatabaseUsername, $DatabasePassword, $DatabaseName);
            break;
    }

    // Error code for both.
    if ($connection === false) {
        switch ($DatabaseType) {
            case 'mysqli':
                $errormessage = mysqli_error($connection);
                break;
        }
        db_show_error("", "Could not Connect to Database: $DatabaseServer", $errstring);
    }
    return $connection;
}

// This function connects, and does the passed query, then returns a connection identifier.
// Not receiving the return == unusable search.
//		ie, $processable_results = DBQuery("select * from students");
function DBQuery($sql) {
    global $DatabaseType, $_openSIS;

    $connection = db_start();

    switch ($DatabaseType) {
        case 'mysqli':
            $sql = str_replace('&amp;', "", $sql);
            $sql = str_replace('&quot', "", $sql);
            $sql = str_replace('&#039;', "", $sql);
            $sql = str_replace('&lt;', "", $sql);
            $sql = str_replace('&gt;', "", $sql);
            $sql = par_rep("/([,\(=])[\r\n\t ]*''/", '\\1NULL', $sql);
            if (preg_match_all("/'(\d\d-[A-Za-z]{3}-\d{2,4})'/", $sql, $matches)) {
                foreach ($matches[1] as $match) {
                    $dt = date('Y-m-d', strtotime($match));
                    $sql = par_rep("/'$match'/", "'$dt'", $sql);
                }
            }
            if (substr($sql, 0, 6) == "BEGIN;") {
                $array = explode(";", $sql);
                foreach ($array as $value) {
                    if ($value != "") {
                        $result = $connection->query($value);
                        if (!$result) {
                            $connection->query("ROLLBACK");
                            die(db_show_error($sql, "DB Execute Failed.", mysql_error()));
                        }
                    }
                }
            } else {
                $result = $connection->query($sql) or die(db_show_error($sql, "DB Execute Failed.", mysql_error()));
            }
            break;
    }
    return $result;
}

// return next row.
function db_fetch_row($result) {
    global $DatabaseType;

    switch ($DatabaseType) {
        case 'mysqli':
            $return = $result->fetch_assoc();
            if (is_array($return)) {
                foreach ($return as $key => $value) {
                    if (is_int($key))
                        unset($return[$key]);
                }
            }
            break;
    }
    return @array_change_key_case($return, CASE_UPPER);
}

// returns code to go into SQL statement for accessing the next value of a sequence function db_seq_nextval($seqname)
function db_seq_nextval($seqname) {
    global $DatabaseType;

    if ($DatabaseType == 'mysqli')
        $seq = "fn_" . strtolower($seqname) . "()";

    return $seq;
}

function db_case($array) {
    global $DatabaseType;

    $counter = 0;
    if ($DatabaseType == 'mysqli') {
        $array_count = count($array);
        $string = " CASE WHEN $array[0] =";
        $counter++;
        $arr_count = count($array);
        for ($i = 1; $i < $arr_count; $i++) {
            $value = $array[$i];

            if ($value == "''" && substr($string, -1) == '=') {
                $value = ' IS NULL';
                $string = substr($string, 0, -1);
            }

            $string.="$value";
            if ($counter == ($array_count - 2) && $array_count % 2 == 0)
                $string.=" ELSE ";
            elseif ($counter == ($array_count - 1))
                $string.=" END ";
            elseif ($counter % 2 == 0)
                $string.=" WHEN $array[0]=";
            elseif ($counter % 2 == 1)
                $string.=" THEN ";

            $counter++;
        }
    }
    return $string;
}

function db_properties($table) {
    global $DatabaseType, $DatabaseUsername;

    switch ($DatabaseType) {
        case 'mysqli':
            $result = DBQuery("SHOW COLUMNS FROM $table");
            while ($row = db_fetch_row($result)) {
                $properties[strtoupper($row['FIELD'])]['TYPE'] = strtoupper($row['TYPE'], strpos($row['TYPE'], '('));
                if (!$pos = strpos($row['TYPE'], ','))
                    $pos = strpos($row['TYPE'], ')');
                else
                    $properties[strtoupper($row['FIELD'])]['SCALE'] = substr($row['TYPE'], $pos + 1);

                $properties[strtoupper($row['FIELD'])]['SIZE'] = substr($row['TYPE'], strpos($row['TYPE'], '(') + 1, $pos);

                if ($row['NULL'] != '')
                    $properties[strtoupper($row['FIELD'])]['NULL'] = "Y";
                else
                    $properties[strtoupper($row['FIELD'])]['NULL'] = "N";
            }
            break;
    }
    return $properties;
}

function db_show_error($sql, $failnote, $additional = '') {
    global $openSISTitle, $openSISVersion, $openSISNotifyAddress, $openSISMode;

    $tb = debug_backtrace();
    $error = $tb[1]['file'] . " at " . $tb[1]['line'];

    echo "
                    <TABLE CELLSPACING=10 BORDER=0>
                            <TD align=right><b>Date:</TD>
                            <TD><pre>" . date("m/d/Y h:i:s") . "</pre></TD>
                    </TR><TR>
                            <TD align=right><b>Failure Notice:</b></TD>
                            <TD><pre> $failnote </pre></TD>
                    </TR><TR>
                            <TD align=right><b>SQL:</b></TD>
                            <TD>$sql</TD>
                    </TR>
                    </TR><TR>
                            <TD align=right><b>Traceback:</b></TD>
                            <TD>$error</TD>
                    </TR>
                    </TR><TR>
                            <TD align=right><b>Additional Information:</b></TD>
                            <TD>$additional</TD>
                    </TR>
                    </TABLE>";

    echo "
		<TABLE CELLSPACING=10 BORDER=0>
			<TR><TD align=right><b>Date:</TD>
			<TD><pre>" . date("m/d/Y h:i:s") . "</pre></TD>
		</TR><TR>
			<TD align=right></TD>
			<TD>openSIS has encountered an error that could have resulted from any of the following:
			<br/>
			<ul>
			<li>Invalid data input</li>
			<li>Database SQL error</li>
			<li>Program error</li>
			</ul>
			
			Please take this screen shot and send it to your openSIS representative for debugging and resolution.
			</TD>
		</TR>
		
		</TABLE>";

    echo "<!-- SQL STATEMENT: \n\n $sql \n\n -->";

    if ($openSISNotifyAddress) {
        $message = "System: $openSISTitle \n";
        $message .= "Date: " . date("m/d/Y h:i:s") . "\n";
        $message .= "Page: " . $_SERVER['PHP_SELF'] . ' ' . ProgramTitle() . " \n\n";
        $message .= "Failure Notice:  $failnote \n";
        $message .= "Additional Info: $additional \n";
        $message .= "\n $sql \n";
        $message .= "Request Array: \n" . ShowVar($_REQUEST, 'Y', 'N');
        $message .= "\n\nSession Array: \n" . ShowVar($_SESSION, 'Y', 'N');
        mail($openSISNotifyAddress, 'openSIS Database Error', $message);
    }

    die();
}

$log_msg = DBGet(DBQuery("SELECT MESSAGE FROM login_message WHERE DISPLAY='Y'"));
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Student Information System</title>
        <link rel="shortcut icon" href="favicon.ico">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700,400italic,600italic" rel="stylesheet" type="text/css">
        <link href="styles/fonts/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="assets/css/icons/icomoon/styles.css" rel="stylesheet" type="text/css">
        <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="assets/css/login.css">
        <script src='js/Ajaxload.js'></script>
        <script src='js/Validation.js'></script>
        <script src='js/Validator.js'></script>
        <script src='js/ForgotPass.js'></script>
        <script type='text/javascript'>
            function init(param, param2) {
                calendar.set('date_' + param);
                document.getElementById('date_' + param).click();
            }
        </script>

        <script src="assets/js/core/libraries/jquery.min.js"></script>
        <script src="assets/js/core/libraries/bootstrap.min.js"></script>
        <script src="assets/js/plugins/ui/moment/moment.min.js"></script>
        <script src="assets/js/plugins/pickers/datepicker.js"></script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('.daterange-single').datepicker({
                    autoclose: true
                });
                $('.daterange-single').datepicker().on('changeDate', function(ev, picker) {
                    var dateText = $(this).val();
                    var pieces = dateText.split("/");
                    var getID = $(this).attr('id').split("_");
                    var parent = $(this).closest('.form-group');
                    parent.children("#monthSelect" + getID[1]).val(pieces[0]);
                    parent.children("#daySelect" + getID[1]).val(pieces[1]);
                    parent.children("#yearSelect" + getID[1]).val(pieces[2]);
                });

                $('.daterange-single').each(function () {
                    var placeholderText = $(this).attr('data-placeholder');
                    $('.daterange-single').attr('placeholder', placeholderText).val('');
                });

                var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                var dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]


                var newDate = new Date();

                newDate.setDate(newDate.getDate());

                $('#Date').html(dayNames[newDate.getDay()] + ", " + monthNames[newDate.getMonth()] + ' ' + newDate.getDate() + ', ' + newDate.getFullYear());



                setInterval(function () {
                    // Create a newDate() object and extract the minutes of the current time on the visitor's
                    var minutes = new Date().getMinutes();
                    // Add a leading zero to the minutes value
                    $("#min").html((minutes < 10 ? "0" : "") + minutes);
                }, 1000);

                setInterval(function () {
                    // Create a newDate() object and extract the hours of the current time on the visitor's
                    var hours = new Date().getHours();
                    // Add a leading zero to the hours value
                    $("#hours").html((hours < 10 ? "0" : "") + hours);
                }, 1000);
            });
        </script>
        <!--custom script-->
        <script src="js/custom.js"></script>
    </head>
    <body>

        <div class="clock">
            <ul>
                <li id="hours"></li>
                <li id="point">:</li>
                <li id="min"></li>
            </ul>
            <div id="Date"></div>
        </div>
        <section class="section-forgot-password">
            <div class="login-wrapper clearfix">

                <div class="panel">

                    <div class="panel-heading">
                        <div class="logo">
                            <img src="assets/images/opensislogo.png" alt="Beelab" />
                            <h2>Beelab</h2>
                        </div>
                    </div>

                    <div class="panel-inside">
                        <div class="panel-heading">
                            <ul class="forgot-tabs clearfix">
                                <li class="active"><a data-toggle="tab" href="#forgot_password">Forgot Password</a></li>
                                <li><a data-toggle="tab" href="#forgot_username">Forgot Username</a></li>
                            </ul>
                        </div>
                        <div class="panel-body">

                            <div class="tab-content">
                                <div id="forgot_password" class="tab-pane fade in active">
                                    <form name="f1" id="f1" method="post" action="ResetUserInfo.php">
                                        <div class="form-group">
                                            <label>I am a</label>
                                            <div class="radio styled-radio">
                                                <label><input type="radio" name="pass_user_type" id="pass_student" value="pass_student" checked="checked" onclick="show_fields('student');
                forgotpassusername_init(this.value);" /><span></span>Student</label>
                                                <label><input type="radio" name="pass_user_type" id="pass_staff" value="pass_staff" onclick="show_fields('staff');
                                                        forgotpassusername_init(this.value);
                                                        forgotpassemail_init('pass_email');" /><span></span>Staff</label>
                                                <label><input type="radio" name="pass_user_type" id="pass_parent" value="pass_parent" onclick="show_fields('parent');
                                                        forgotpassusername_init(this.value);
                                                        forgotpassemail_init('pass_email');" /><span></span>Parent</label>
                                            </div>
                                            <input type="hidden" name="pass_type_form" id="pass_type_form" value="password"/>

                                        </div>
                                        <input type="hidden" id="valid_func" value="N"/>
                                        <div id="divErr">
                                            <?php
                                            if ($_SESSION['err_msg'] != '')
                                                echo $_SESSION['err_msg'];
                                            unset($_SESSION['err_msg']);
                                            ?>
                                        </div>

                                        <div class="form-group" id="pass_stu_id">
                                            <input type="text" name="password_stn_id" id="password_stn_id" placeholder="Student ID" class="form-control" onkeydown="return numberOnly(event);" onblur="return check_input_val(this.value, 'password_stn_id');"/>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" name="uname" id="uname" placeholder="Username" class="form-control" onkeydown=" return withoutspace_forgotpass(event);" onblur="return withoutspace_forgotpass(event);"/>
                                            <span style="display: none" id="calculating"><img src="assets/ajax_loader.gif"/></span>
                                            <p id="err_msg"></p>
                                        </div>
                                        <div class="form-group" id="pass_stu_dob">
                                            <?php echo DateInputAY('', 'password_dob', 1, 'Date of Birth') ?>
                                        </div>
                                        <div id="pass_stf_email" class="form-group" style="display: none">
                                            <input type="hidden" name="pass_email" id="pass_email" value=""/>
                                            <input type="text" name="password_stf_email" id="password_stf_email" placeholder="Email Address" class="form-control" onblur="forgotpassemail_init('pass_email');" />
                                            <span style="display: none" id="pass_calculating_email"><img src="assets/ajax_loader.gif"/></span>
                                            <span id="pass_err_msg_email"></span>
                                        </div>
                                        <div class="row">
                                            <div class="col-xs-6">
                                                <input type="submit" class="btn btn-success btn-lg btn-block" value="Confirm" onClick="return forgotpass();" />
                                            </div>
                                            <div class="col-xs-6">
                                                <a href="index.php" class="btn btn-default btn-rounded btn-lg btn-block">Cancel</a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div id="forgot_username" class="tab-pane fade">
                                    <form name="f1" id="f1" method="post" action="ResetUserInfo.php">
                                        <div class="form-group">
                                            <label>I am a</label>
                                            <div class="radio styled-radio">
                                                <label onclick="uname_show_fields('student')"><input type="radio" name="uname_user_type" id="uname_student" value="uname_student" checked="checked" /><span></span>Student</label>
                                                <label onclick="uname_show_fields('staff');
                                                        forgotpassemail_init('uname_email');"><input type="radio" name="uname_user_type" id="uname_staff" value="uname_staff" /><span></span>Staff</label>
                                                <label onclick="uname_show_fields('parent');
                                                        forgotpassemail_init('uname_email');"><input type="radio" name="uname_user_type" id="uname_parent" value="uname_parent" /><span></span>Parent</label>
                                            </div>                                            
                                            <input type="hidden" name="user_type_form" id="user_type_form" value="username" disabled="disabled"/>                                            

                                        </div>
                                        <div class="form-group" id="uname_stu_id">
                                            <input type="text" name="username_stn_id" id="username_stn_id" class="form-control" placeholder="Student ID" onblur="return check_input_val(this.value, 'username_stn_id');" onkeydown="return numberOnly(event);"/>
                                        </div>
                                        <div class="form-group">
                                            <input type="password" name="pass" id="pass" class="form-control" placeholder="Password" />
                                        </div>
                                        <div class="form-group" id="uname_stu_dob">
                                            <?php echo DateInputAY('', 'username_dob', 2, 'Date of Birth') ?>
                                        </div>                                        
                                        <div class="form-group" id="uname_stf_email" style="display: none">
                                            <input type="hidden" name="un_email" id="un_email" value=""/>
                                            <input type="text" name="username_stf_email" id="username_stf_email" class="form-control" placeholder="Email Address" onblur="forgotpassemail_init('uname_email');" />
                                            <span style="display: none" id="uname_calculating_email"><img src="assets/ajax_loader.gif"/></span>
                                            <span id="uname_err_msg_email"></span>
                                        </div>
                                        <div class="row">
                                            <div class="col-xs-6">
                                                <input type="submit" class="btn btn-success btn-lg btn-block" name="save" onClick="return forgotusername();" value="Confirm" />
                                            </div>
                                            <div class="col-xs-6">
                                                <a href="index.php" class="btn btn-default btn-rounded btn-lg btn-block">Cancel</a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            </form>
                        </div>
                    </div>
                    <!--                    <div class="panel-footer">
                                            <p class="info-text"><?php echo $log_msg[1]['MESSAGE']; ?></p>
                                        </div>-->
                </div>
                <footer>
                    Beelab School information system 
                </footer>
            </div>
        </section>

    </body>
</html>
