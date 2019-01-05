<?php /*
    LibreSSL - CAcert web application
    Copyright (C) 2004-2008  CAcert Inc.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

include_once($_SESSION['_config']['filepath']."/includes/notary.inc.php");

$ticketno='';
$ticketvalidation=FALSE;

if (isset($_SESSION['ticketno'])) {
    $ticketno = $_SESSION['ticketno'];
    $ticketvalidation = valid_ticket_number($ticketno);
}
if (isset($_SESSION['ticketmsg'])) {
    $ticketmsg = $_SESSION['ticketmsg'];
} else {
    $ticketmsg = '';
}


// search for an account by email search, if more than one is found display list to choose
if(intval(array_key_exists('userid',$_REQUEST)?$_REQUEST['userid']:0) <= 0)
{
    $_REQUEST['userid'] = 0;

    $emailsearch = $email = mysqli_real_escape_string($_SESSION['mconn'], stripslashes($_REQUEST['email']));

    //Disabled to speed up the queries
    //if(!strstr($email, "%"))
    //  $emailsearch = "%$email%";

    // bug-975 ted+uli changes --- begin
    if(preg_match("/^[0-9]+$/", $email)) {
        // $email consists of digits only ==> search for IDs
        // Be defensive here (outer join) if primary mail is not listed in email table
        $query = "select `users`.`id` as `id`, `email`.`email` as `email`
            from `users` left outer join `email` on (`users`.`id`=`email`.`memid`)
            where (`email`.`id`='$email' or `users`.`id`='$email')
                and `users`.`deleted`=0
            group by `users`.`id` limit 100";
    } else {
        // $email contains non-digits ==> search for mail addresses
        // Be defensive here (outer join) if primary mail is not listed in email table
        $query = "select `users`.`id` as `id`, `email`.`email` as `email`
            from `users` left outer join `email` on (`users`.`id`=`email`.`memid`)
            where (`email`.`email` like '$emailsearch'
                    or `users`.`email` like '$emailsearch')
                and `users`.`deleted`=0
            group by `users`.`id` limit 100";
    }
    // bug-975 ted+uli changes --- end
    $res = mysqli_query($_SESSION['mconn'], $query);
    if(mysqli_num_rows($res) > 1) {
?>
        <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
            <tr>
                <td colspan="5" class="title"><?php echo _("Select Specific Account Details")?></td>
            </tr>
            <tr>
                <td class="DataTD"><?php echo _("User ID")?></td>
                <td class="DataTD"><?php echo _("Email")?></td>
            </tr>
<?php         while($row = mysqli_fetch_assoc($res))
        {
?>
            <tr>
                <td class="DataTD"><a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>"><?php echo intval($row['id'])?></a></td>
                <td class="DataTD"><a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>"><?php echo sanitizeHTML($row['email'])?></a></td>
            </tr>
<?php         }

        if(mysqli_num_rows($res) >= 100) {
?>
            <tr>
                <td class="DataTD" colspan="2"><?php echo _("Only the first 100 rows are displayed.")?></td>
            </tr>
<?php         } else {
?>
            <tr>
                <td class="DataTD" colspan="2"><?php printf(_("%s rows displayed."), mysqli_num_rows($res)); ?></td>
            </tr>
<?php         }
?>
        </table><br><br>
<?php     } elseif(mysqli_num_rows($res) == 1) {
        $row = mysqli_fetch_assoc($res);
        $_REQUEST['userid'] = $row['id'];
    } else {
        printf(_("No users found matching %s"), sanitizeHTML($email));
    }
}

// display user information for given user id
if(intval($_REQUEST['userid']) > 0) {
    $userid = intval($_REQUEST['userid']);
    $res =get_user_data($userid);
    if(mysqli_num_rows($res) <= 0) {
        echo _("I'm sorry, the user you were looking for seems to have disappeared! Bad things are afoot!");
    } else {
        $row = mysqli_fetch_assoc($res);
        $query = "select sum(`points`) as `points` from `notary` where `to`='".intval($row['id'])."' and `deleted` = 0";
        $dres = mysqli_query($_SESSION['mconn'], $query);
        $drow = mysqli_fetch_assoc($dres);
        $alerts =get_alerts(intval($row['id']));

//display account data

//deletes an assurance
        if(array_key_exists('assurance',$_REQUEST) && $_REQUEST['assurance'] > 0 && $ticketvalidation == true)
        {
            if (!write_se_log($userid, $_SESSION['profile']['id'], 'SE assurance revoke', $ticketno)) {
                $ticketmsg=_("Writing to the admin log failed. Can't continue.");
            } else {
                $assurance = intval($_REQUEST['assurance']);
                $trow = 0;
                $res = mysqli_query($_SESSION['mconn'], "select `to` from `notary` where `id`='".intval($assurance)."' and `deleted` = 0");
                if ($res) {
                    $trow = mysqli_fetch_assoc($res);
                    if ($trow) {
                        mysqli_query($_SESSION['mconn'], "update `notary` set `deleted`=NOW() where `id`='".intval($assurance)."'");
                        fix_assurer_flag($trow['to']);
                    }
                }
            }
        } elseif(array_key_exists('assurance',$_REQUEST) && $_REQUEST['assurance'] > 0 && $ticketvalidation == FALSE) {
            $ticketmsg=_('No assurance revoked. Ticket number is missing!');
        }

//Ticket number
?>

<form method="post" action="account.php?id=43&userid=<?php echo intval($_REQUEST['userid'])?>">
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="2" class="title"><?php echo _('Ticket handling') ?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _('Ticket no')?>:</td>
            <td class="DataTD"><input type="text" name="ticketno" value="<?php echo sanitizeHTML($ticketno)?>"/></td>
        </tr>
        <tr>
            <td colspan="2" class="DataTDError"><?php echo $ticketmsg?></td><?php $_SESSION['ticketmsg']='' ?>
        </tr>
        <tr>
            <td colspan="2" ><input type="submit" value="<?php echo _('Set ticket number') ?>"></td>
        </tr>
    </table>
</form>
<br/>


<!-- display data table -->
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="5" class="title"><?php printf(_("%s's Account Details"), sanitizeHTML($row['email'])); ?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Email")?>:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['email'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("First Name")?>:</td>
            <td class="DataTD"><form method="post" action="account.php" onSubmit="if(!confirm('<?php echo _("Are you sure you want to modify this DOB and/or last name?")?>')) return false;">
                <input type="hidden" name="csrf" value="<?php echo make_csrf('admchangepers')?>" />
                <input type="text" name="fname" value="<?php echo sanitizeHTML($row['fname'])?>">
            </td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Middle Name")?>:</td>
            <td class="DataTD"><input type="text" name="mname" value="<?php echo sanitizeHTML($row['mname'])?>"></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Last Name")?>:</td>
            <td class="DataTD">  <input type="hidden" name="oldid" value="43">
                <input type="hidden" name="action" value="updatedob">
                <input type="hidden" name="userid" value="<?php echo intval($userid)?>">
                <input type="text" name="lname" value="<?php echo sanitizeHTML($row['lname'])?>">
            </td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Suffix")?>:</td>
            <td class="DataTD"><input type="text" name="suffix" value="<?php echo sanitizeHTML($row['suffix'])?>"></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Date of Birth")?>:</td>
            <td class="DataTD">
                <?php                 $year = intval(substr($row['dob'], 0, 4));
                $month = intval(substr($row['dob'], 5, 2));
                $day = intval(substr($row['dob'], 8, 2));
    ?>
                <nobr>
                        <select name="day">
    <?php                 for($i = 1; $i <= 31; $i++) {
                    echo "<option";
                    if($day == $i) {
                        echo " selected='selected'";
                    }
                    echo ">$i</option>";
                }
    ?>
                        </select>
                        <select name="month">
    <?php                 for($i = 1; $i <= 12; $i++) {
                    echo "<option value='$i'";
                    if($month == $i)
                            echo " selected='selected'";
                    echo ">".ucwords(strftime("%B", mktime(0,0,0,$i,1,date("Y"))))."</option>";
                }
    ?>
                        </select>
                        <input type="text" name="year" value="<?php echo $year?>" size="4">
                        <input type="submit" value="Go">
                        <input type="hidden" name="ticketno" value="<?php echo sanitizeHTML($ticketno)?>"/>
                    </form>
                </nobr>
            </td>
        </tr>

    <?php // list of flags ?>
        <tr>
            <td class="DataTD"><?php echo _("CCA accepted")?>:</td>
            <td class="DataTD"><a href="account.php?id=57&amp;userid=<?php echo intval($row['id'])?>"><?php echo intval(get_user_agreement_status($row['id'], 'CCA')) ? _("Yes") : _("No") ?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Trainings")?>:</td>
            <td class="DataTD"><a href="account.php?id=55&amp;userid=<?php echo intval($row['id'])?>">show</a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Is Assurer")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;assurer=<?php echo intval($row['id'])?>&amp;csrf=<?php echo make_csrf('admsetassuret')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['assurer'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Blocked Assurer")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;assurer_blocked=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['assurer_blocked'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Account Locking")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;locked=<?php echo intval($row['id'])?>&amp;csrf=<?php echo make_csrf('admactlock')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['locked'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Code Signing")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;codesign=<?php echo intval($row['id'])?>&amp;csrf=<?php echo make_csrf('admcodesign')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['codesign'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Org Assurer")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;orgadmin=<?php echo intval($row['id'])?>&amp;csrf=<?php echo make_csrf('admorgadmin')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['orgadmin'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("TTP Admin")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;ttpadmin=<?php echo intval($row['id'])?>&amp;csrf=<?php echo make_csrf('admttpadmin')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['ttpadmin'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Location Admin")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;locadmin=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo $row['locadmin']?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Admin")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;admin=<?php echo intval($row['id'])?>&amp;csrf=<?php echo make_csrf('admsetadmin')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['admin'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Ad Admin")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;adadmin=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['adadmin'])?></a> (0 = none, 1 = submit, 2 = approve)</td>
        </tr>
    <!-- presently not needed
        <tr>
            <td class="DataTD"><?php echo _("Tverify Account")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;tverify=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($row['tverify'])?></a></td>
        </tr>
    -->
        <tr>
            <td class="DataTD"><?php echo _("General Announcements")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;general=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($alerts['general'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Country Announcements")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;country=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($alerts['country'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Regional Announcements")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;regional=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($alerts['regional'])?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Within 200km Announcements")?>:</td>
            <td class="DataTD"><a href="account.php?id=43&amp;radius=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo intval($alerts['radius'])?></a></td>
        </tr>
    <?php //change password, view secret questions and delete account section ?>
        <tr>
            <td class="DataTD"><?php echo _("Change Password")?>:</td>
            <td class="DataTD"><a href="account.php?id=44&amp;userid=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("Change Password")?></a></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Delete Account")?>:</td>
            <td class="DataTD"><a href="account.php?id=50&amp;userid=<?=intval($row['id'])?>&amp;csrf=<?=make_csrf('admdelaccount')?>&amp;ticketno=<?=sanitizeHTML($ticketno)?>"><?=_("Delete Account")?></a></td>
        </tr>
    <?php                 // This is intensionally a $_GET for audit purposes. DO NOT CHANGE!!!
                if(array_key_exists('showlostpw',$_GET) && $_GET['showlostpw'] == "yes" && $ticketvalidation==true) {
                    if (!write_se_log($userid, $_SESSION['profile']['id'], 'SE view lost password information', $ticketno)) {
    ?>
        <tr>
            <td class="DataTD" colspan="2"><?php echo _("Writing to the admin log failed. Can't continue.")?></td>
        </tr>
        <tr>
            <td class="DataTD" colspan="2"><a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;showlostpw=yes&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("Show Lost Password Details")?></a></td>
        </tr>
    <?php                     } else {
    ?>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - Q1:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['Q1'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - A1:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['A1'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - Q2:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['Q2'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - A2:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['A2'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - Q3:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['Q3'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - A3:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['A3'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - Q4:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['Q4'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - A4:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['A4'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - Q5:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['Q5'])?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Lost Password")?> - A5:</td>
            <td class="DataTD"><?php echo sanitizeHTML($row['A5'])?></td>
        </tr>
    <?php                     }
                } elseif (array_key_exists('showlostpw',$_GET) && $_GET['showlostpw'] == "yes" && $ticketvalidation==false) {
    ?>
        <tr>
            <td class="DataTD" colspan="2"><?php echo _('No access granted. Ticket number is missing')?></td>
        </tr>
        <tr>
            <td class="DataTD" colspan="2"><a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;showlostpw=yes&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("Show Lost Password Details")?></a></td>
        </tr>
    <?php                 } else {
                    ?>
        <tr>
            <td class="DataTD" colspan="2"><a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;showlostpw=yes&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("Show Lost Password Details")?></a></td>
        </tr>
    <?php                }

    // list assurance points
    ?>
        <tr>
            <td class="DataTD"><?php echo _("Assurance Points")?>:</td>
            <td class="DataTD"><?php echo intval($drow['points'])?></td>
        </tr>
    <?php     // show account history
    ?>
        <tr>
            <td class="DataTD" colspan="2"><a href="account.php?id=59&amp;oldid=43&amp;userid=<?php echo intval($row['id'])?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _('Show account history')?></a></td>
        </tr>
    </table>
    <br/>
    <?php     //list secondary email addresses
                $dres = get_email_addresses(intval($row['id']),$row['email']);
                if(mysqli_num_rows($dres) > 0) {
    ?>
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="5" class="title"><?php echo _("Alternate Verified Email Addresses")?></td>
        </tr>
    <?php                     while($drow = mysqli_fetch_assoc($dres)) {
    ?>
        <tr>
            <td class="DataTD"><?php echo _("Secondary Emails")?>:</td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['email'])?></td>
        </tr>
    <?php                     }
    ?>
    </table>
    <br/>
    <?php                 }

    // list of domains
                $dres=get_domains(intval($row['id']));
                if(mysqli_num_rows($dres) > 0) {
    ?>
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="5" class="title"><?php echo _("Verified Domains")?></td>
        </tr>
    <?php                     while($drow = mysqli_fetch_assoc($dres)) {
    ?>
        <tr>
            <td class="DataTD"><?php echo _("Domain")?>:</td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['domain'])?></td>
        </tr>
    <?php                     }
    ?>
    </table>
    <br/>
    <?php                 }
    ?>
    <?php //  Begin - Debug infos ?>
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="2" class="title"><?php echo _("Account State")?></td>
        </tr>

    <?php                 // ---  bug-975 begin ---
                //  potential db inconsistency like in a20110804.1
                //    Admin console -> don't list user account
                //    User login -> impossible
                //    Assurer, assure someone -> user displayed
                /*  regular user account search with regular settings

                --- Admin Console find user query
                $query = "select `users`.`id` as `id`, `email`.`email` as `email` from `users`,`email`
                    where `users`.`id`=`email`.`memid` and
                    (`email`.`email` like '$emailsearch' or `email`.`id`='$email' or `users`.`id`='$email') and
                    `email`.`hash`='' and `email`.`deleted`=0 and `users`.`deleted`=0
                    group by `users`.`id` limit 100";
                 => requirements
                   1.  email.hash = ''
                   2.  email.deleted = 0
                   3.  users.deleted = 0
                   4.  email.email = primary-email       (???) or'd
                  not covered by admin console find user routine, but may block users login
                   5.  users.verified = 0|1
                  further "special settings"
                   6.  users.locked  (setting displayed in display form)
                   7.  users.assurer_blocked   (setting displayed in display form)

                --- User login user query
                select * from `users` where `email`='$email' and (`password`=old_password('$pword') or `password`=sha1('$pword') or
                    `password`=password('$pword')) and `verified`=1 and `deleted`=0 and `locked`=0
                 => requirements
                   1. users.verified = 1
                   2. users.deleted = 0
                   3. users.locked = 0
                   4. users.email = primary-email

                --- Assurer, assure someone find user query
                select * from `users` where `email`='".mysqli_real_escape_string($_SESSION['mconn'], $_POST['email']))."'
                    and `deleted`=0
                 => requirements
                   1. users.deleted = 0
                   2. users.email = primary-email

                                                 Admin      User        Assurer
                  bit                            Console    Login       assure someone

                   1.  email.hash = ''            Yes        No           No
                   2.  email.deleted = 0          Yes        No           No
                   3.  users.deleted = 0          Yes        Yes          Yes
                   4.  users.verified = 1         No         Yes          No
                   5.  users.locked = 0           No         Yes          No
                   6.  users.email = prim-email   No         Yes          Yes
                   7.  email.email = prim-email   Yes        No           No

                full usable account needs all 7 requirements fulfilled
                so if one setting isn't set/cleared there is an inconsistency either way
                if eg email.email is not avail, admin console cannot open user info
                but user can login and assurer can display user info
                if user verified is not set to 1, admin console displays user record
                but user cannot login, but assurer can search for the user and the data displays

                consistency check:
                1. search primary-email in users.email
                2. search primary-email in email.email
                3. userid = email.memid
                4. check settings from table 1. - 5.

                */

                $inconsistency = 0;
                $inconsistencydisp = "";
                $inccause = "";

                // current userid  intval($row['id'])
                $query = "select `email` as `uemail`, `deleted` as `udeleted`, `verified`, `locked`
                    from `users` where `id`='".intval($row['id'])."' ";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                $drow = mysqli_fetch_assoc($dres);
                $uemail    = $drow['uemail'];
                $udeleted  = $drow['udeleted'];
                $uverified = $drow['verified'];
                $ulocked   = $drow['locked'];

                $query = "select `hash`, `email` as `eemail` from `email`
                    where `memid`='".intval($row['id'])."' and
                        `email` ='".$uemail."' and
                        `deleted` = 0";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                if ($drow = mysqli_fetch_assoc($dres)) {
                    $drow['edeleted'] = 0;
                } else {
                    // try if there are deleted entries
                    $query = "select `hash`, `deleted` as `edeleted`, `email` as `eemail` from `email`
                        where `memid`='".intval($row['id'])."' and
                            `email` ='".$uemail."'";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                }

                if ($drow) {
                    $eemail    = $drow['eemail'];
                    $edeleted  = $drow['edeleted'];
                    $ehash     = $drow['hash'];
                    if ($udeleted!=0) {
                        $inconsistency += 1;
                        $inccause .= (empty($inccause)?"":"<br>")._("Users record set to deleted");
                    }
                    if ($uverified!=1) {
                        $inconsistency += 2;
                        $inccause .= (empty($inccause)?"":"<br>")._("Users record verified not set");
                    }
                    if ($ulocked!=0) {
                        $inconsistency += 4;
                        $inccause .= (empty($inccause)?"":"<br>")._("Users record locked set");
                    }
                    if ($edeleted!=0) {
                        $inconsistency += 8;
                        $inccause .= (empty($inccause)?"":"<br>")._("Email record set deleted");
                    }
                    if ($ehash!='') {
                        $inconsistency += 16;
                        $inccause .= (empty($inccause)?"":"<br>")._("Email record hash not unset");
                    }
                } else {
                    $inconsistency = 32;
                    $inccause = _("Prim. email, Email record doesn't exist");
                }
                if ($inconsistency>0) {
                    // $inconsistencydisp = _("Yes");
    ?>
        <tr>
            <td class="DataTD"><?php echo _("Account inconsistency")?>:</td>
            <td class="DataTD"><?php echo $inccause?><br>code: <?php echo intval($inconsistency)?></td>
        </tr>
        <tr>
            <td colspan="2" class="DataTD" style="max-width: 75ex;">
                <?php echo _("Account inconsistency can cause problems in daily account operations and needs to be fixed manually through arbitration/critical team.")?>
            </td>
        </tr>
    <?php                 }

                // ---  bug-975 end ---
    ?>
    </table>
    <br />
    <?php     //  End - Debug infos

    // certificate overview
    ?>

    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="6" class="title"><?php echo _("Certificates")?></td>
        </tr>
        <tr>
            <td class="DataTD"><?php echo _("Cert Type")?>:</td>
            <td class="DataTD"><?php echo _("Total")?></td>
            <td class="DataTD"><?php echo _("Valid")?></td>
            <td class="DataTD"><?php echo _("Expired")?></td>
            <td class="DataTD"><?php echo _("Revoked")?></td>
            <td class="DataTD"><?php echo _("Latest Expire")?></td>
        </tr>
        <!-- server certificates -->
        <tr>
            <td class="DataTD"><?php echo _("Server")?>:</td>
    <?php                 $query = "
                    select COUNT(*) as `total`,
                        MAX(`domaincerts`.`expire`) as `maxexpire`
                    from `domains` inner join `domaincerts`
                        on `domains`.`id` = `domaincerts`.`domid`
                    where `domains`.`memid` = '".intval($row['id'])."'
                    ";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                $drow = mysqli_fetch_assoc($dres);
                $total = $drow['total'];

                $maxexpire = "0000-00-00 00:00:00";
                if ($drow['maxexpire']) {
                    $maxexpire = $drow['maxexpire'];
                }

                if($total > 0) {
                    $query = "
                        select COUNT(*) as `valid`
                        from `domains` inner join `domaincerts`
                            on `domains`.`id` = `domaincerts`.`domid`
                        where `domains`.`memid` = '".intval($row['id'])."'
                            and `revoked` = '0000-00-00 00:00:00'
                            and `expire` > NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $valid = $drow['valid'];

                    $query = "
                        select COUNT(*) as `expired`
                        from `domains` inner join `domaincerts`
                            on `domains`.`id` = `domaincerts`.`domid`
                        where `domains`.`memid` = '".intval($row['id'])."'
                            and `expire` <= NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $expired = $drow['expired'];

                    $query = "
                        select COUNT(*) as `revoked`
                        from `domains` inner join `domaincerts`
                            on `domains`.`id` = `domaincerts`.`domid`
                        where `domains`.`memid` = '".intval($row['id'])."'
                            and `revoked` != '0000-00-00 00:00:00'
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $revoked = $drow['revoked'];
    ?>
            <td class="DataTD"><?php echo intval($total)?></td>
            <td class="DataTD"><?php echo intval($valid)?></td>
            <td class="DataTD"><?php echo intval($expired)?></td>
            <td class="DataTD"><?php echo intval($revoked)?></td>
            <td class="DataTD"><?php echo ($maxexpire != "0000-00-00 00:00:00")?substr($maxexpire, 0, 10) : _("Pending")?></td>
    <?php                 } else { // $total > 0
    ?>
            <td colspan="5" class="DataTD"><?php echo _("None")?></td>
    <?php                 }
    ?>
        </tr>
        <!-- client certificates -->
        <tr>
            <td class="DataTD"><?php echo _("Client")?>:</td>
    <?php                 $query = "
                    select COUNT(*) as `total`, MAX(`expire`) as `maxexpire`
                    from `emailcerts`
                    where `memid` = '".intval($row['id'])."'
                    ";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                $drow = mysqli_fetch_assoc($dres);
                $total = $drow['total'];

                $maxexpire = "0000-00-00 00:00:00";
                if ($drow['maxexpire']) {
                    $maxexpire = $drow['maxexpire'];
                }

                if($total > 0) {
                    $query = "
                        select COUNT(*) as `valid`
                        from `emailcerts`
                        where `memid` = '".intval($row['id'])."'
                            and `revoked` = '0000-00-00 00:00:00'
                            and `expire` > NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $valid = $drow['valid'];

                    $query = "
                        select COUNT(*) as `expired`
                        from `emailcerts`
                        where `memid` = '".intval($row['id'])."'
                            and `expire` <= NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $expired = $drow['expired'];

                    $query = "
                        select COUNT(*) as `revoked`
                        from `emailcerts`
                        where `memid` = '".intval($row['id'])."'
                            and `revoked` != '0000-00-00 00:00:00'
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $revoked = $drow['revoked'];
    ?>
            <td class="DataTD"><?php echo intval($total)?></td>
            <td class="DataTD"><?php echo intval($valid)?></td>
            <td class="DataTD"><?php echo intval($expired)?></td>
            <td class="DataTD"><?php echo intval($revoked)?></td>
            <td class="DataTD"><?php echo ($maxexpire != "0000-00-00 00:00:00")?substr($maxexpire, 0, 10) : _("Pending")?></td>
    <?php                 } else { // $total > 0
    ?>
            <td colspan="5" class="DataTD"><?php echo _("None")?></td>
    <?php                 }
    ?>
        </tr>
        <!-- gpg certificates -->
        <tr>
            <td class="DataTD"><?php echo _("GPG")?>:</td>
    <?php                 $query = "
                    select COUNT(*) as `total`, MAX(`expire`) as `maxexpire`
                    from `gpg`
                    where `memid` = '".intval($row['id'])."'
                    ";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                $drow = mysqli_fetch_assoc($dres);
                $total = $drow['total'];

                $maxexpire = "0000-00-00 00:00:00";
                if ($drow['maxexpire']) {
                    $maxexpire = $drow['maxexpire'];
                }

                if($total > 0) {
                    $query = "
                        select COUNT(*) as `valid`
                        from `gpg`
                        where `memid` = '".intval($row['id'])."'
                            and `expire` > NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $valid = $drow['valid'];

                    $query = "
                        select COUNT(*) as `expired`
                        from `gpg`
                        where `memid` = '".intval($row['id'])."'
                            and `expire` <= NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $expired = $drow['expired'];
    ?>
            <td class="DataTD"><?php echo intval($total)?></td>
            <td class="DataTD"><?php echo intval($valid)?></td>
            <td class="DataTD"><?php echo intval($expired)?></td>
            <td class="DataTD"></td>
            <td class="DataTD"><?php echo ($maxexpire != "0000-00-00 00:00:00")?substr($maxexpire, 0, 10) : _("Pending")?></td>
    <?php                 } else { // $total > 0
    ?>
            <td colspan="5" class="DataTD"><?php echo _("None")?></td>
    <?php                 }
    ?>
        </tr>
        <!-- org server certificates -->
        <tr>
            <td class="DataTD"><a href="account.php?id=58&amp;userid=<?php echo intval($row['id'])?>"><?php echo _("Org Server")?></a>:</td>
    <?php                 $query = "
                    select COUNT(*) as `total`,
                        MAX(`orgcerts`.`expire`) as `maxexpire`
                    from `orgdomaincerts` as `orgcerts` inner join `org`
                        on `orgcerts`.`orgid` = `org`.`orgid`
                    where `org`.`memid` = '".intval($row['id'])."'
                    ";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                $drow = mysqli_fetch_assoc($dres);
                $total = $drow['total'];

                $maxexpire = "0000-00-00 00:00:00";
                if ($drow['maxexpire']) {
                    $maxexpire = $drow['maxexpire'];
                }

                if($total > 0) {
                    $query = "
                        select COUNT(*) as `valid`
                        from `orgdomaincerts` as `orgcerts` inner join `org`
                            on `orgcerts`.`orgid` = `org`.`orgid`
                        where `org`.`memid` = '".intval($row['id'])."'
                            and `orgcerts`.`revoked` = '0000-00-00 00:00:00'
                            and `orgcerts`.`expire` > NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $valid = $drow['valid'];

                    $query = "
                        select COUNT(*) as `expired`
                        from `orgdomaincerts` as `orgcerts` inner join `org`
                            on `orgcerts`.`orgid` = `org`.`orgid`
                        where `org`.`memid` = '".intval($row['id'])."'
                            and `orgcerts`.`expire` <= NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $expired = $drow['expired'];

                    $query = "
                        select COUNT(*) as `revoked`
                        from `orgdomaincerts` as `orgcerts` inner join `org`
                            on `orgcerts`.`orgid` = `org`.`orgid`
                        where `org`.`memid` = '".intval($row['id'])."'
                            and `orgcerts`.`revoked` != '0000-00-00 00:00:00'
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $revoked = $drow['revoked'];
    ?>
            <td class="DataTD"><?php echo intval($total)?></td>
            <td class="DataTD"><?php echo intval($valid)?></td>
            <td class="DataTD"><?php echo intval($expired)?></td>
            <td class="DataTD"><?php echo intval($revoked)?></td>
            <td class="DataTD"><?php echo ($maxexpire != "0000-00-00 00:00:00")?substr($maxexpire, 0, 10) : _("Pending")?></td>
    <?php                 } else { // $total > 0
    ?>
            <td colspan="5" class="DataTD"><?php echo _("None")?></td>
    <?php                 }
    ?>
        </tr>
        <!-- org client certificates -->
        <tr>
            <td class="DataTD"><?php echo _("Org Client")?>:</td>
    <?php                 $query = "
                    select COUNT(*) as `total`,
                        MAX(`orgcerts`.`expire`) as `maxexpire`
                    from `orgemailcerts` as `orgcerts` inner join `org`
                        on `orgcerts`.`orgid` = `org`.`orgid`
                    where `org`.`memid` = '".intval($row['id'])."'
                    ";
                $dres = mysqli_query($_SESSION['mconn'], $query);
                $drow = mysqli_fetch_assoc($dres);
                $total = $drow['total'];

                $maxexpire = "0000-00-00 00:00:00";
                if ($drow['maxexpire']) {
                    $maxexpire = $drow['maxexpire'];
                }

                if($total > 0) {
                    $query = "
                        select COUNT(*) as `valid`
                        from `orgemailcerts` as `orgcerts` inner join `org`
                            on `orgcerts`.`orgid` = `org`.`orgid`
                        where `org`.`memid` = '".intval($row['id'])."'
                            and `orgcerts`.`revoked` = '0000-00-00 00:00:00'
                            and `orgcerts`.`expire` > NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $valid = $drow['valid'];

                    $query = "
                        select COUNT(*) as `expired`
                        from `orgemailcerts` as `orgcerts` inner join `org`
                            on `orgcerts`.`orgid` = `org`.`orgid`
                        where `org`.`memid` = '".intval($row['id'])."'
                            and `orgcerts`.`expire` <= NOW()
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $expired = $drow['expired'];

                    $query = "
                        select COUNT(*) as `revoked`
                        from `orgemailcerts` as `orgcerts` inner join `org`
                            on `orgcerts`.`orgid` = `org`.`orgid`
                        where `org`.`memid` = '".intval($row['id'])."'
                            and `orgcerts`.`revoked` != '0000-00-00 00:00:00'
                        ";
                    $dres = mysqli_query($_SESSION['mconn'], $query);
                    $drow = mysqli_fetch_assoc($dres);
                    $revoked = $drow['revoked'];
    ?>
            <td class="DataTD"><?php echo intval($total)?></td>
            <td class="DataTD"><?php echo intval($valid)?></td>
            <td class="DataTD"><?php echo intval($expired)?></td>
            <td class="DataTD"><?php echo intval($revoked)?></td>
            <td class="DataTD"><?php echo ($maxexpire != "0000-00-00 00:00:00")?substr($maxexpire, 0, 10) : _("Pending")?></td>
    <?php                 } else { // $total > 0
    ?>
            <td colspan="5" class="DataTD"><?php echo _("None")?></td>
    <?php                 }
    ?>
        </tr>
        <tr>
            <td colspan="6" class="title">
                <form method="post" action="account.php" onSubmit="if(!confirm('<?php echo _("Are you sure you want to revoke all private certificates?")?>')) return false;">
                    <input type="hidden" name="action" value="revokecert">
                    <input type="hidden" name="oldid" value="43">
                    <input type="hidden" name="userid" value="<?php echo intval($userid)?>">
                    <input type="submit" value="<?php echo _('revoke certificates')?>">
                    <input type="hidden" name="ticketno" value="<?php echo sanitizeHTML($ticketno)?>"/>
                </form>
            </td>
        </tr>
    </table>
    <br />
    <?php // list assurances ?>
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td class="DataTD">
                <a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;shownotary=assuredto&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("Show Assurances the user got")?></a>
                (<a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;shownotary=assuredto15&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("New calculation")?></a>)
            </td>
        </tr>
        <tr>
            <td class="DataTD">
                <a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;shownotary=assuredby&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("Show Assurances the user gave")?></a>
                (<a href="account.php?id=43&amp;userid=<?php echo intval($row['id'])?>&amp;shownotary=assuredby15&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>"><?php echo _("New calculation")?></a>)
            </td>
        </tr>
    </table>
    <?php     //  if(array_key_exists('assuredto',$_GET) && $_GET['assuredto'] == "yes") {


    function showassuredto($ticketno)
    {
    ?>
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="8" class="title"><?php echo _("Assurance Points")?></td>
        </tr>
        <tr>
            <td class="DataTD"><b><?php echo _("ID")?></b></td>
            <td class="DataTD"><b><?php echo _("Date")?></b></td>
            <td class="DataTD"><b><?php echo _("Who")?></b></td>
            <td class="DataTD"><b><?php echo _("Email")?></b></td>
            <td class="DataTD"><b><?php echo _("Points")?></b></td>
            <td class="DataTD"><b><?php echo _("Location")?></b></td>
            <td class="DataTD"><b><?php echo _("Method")?></b></td>
            <td class="DataTD"><b><?php echo _("Revoke")?></b></td>
        </tr>
    <?php         $query = "select * from `notary` where `to`='".intval($_GET['userid'])."'  and `deleted` = 0";
        $dres = mysqli_query($_SESSION['mconn'], $query);
        $points = 0;
        while($drow = mysqli_fetch_assoc($dres)) {
            $fromuser = mysqli_fetch_assoc(mysqli_query($_SESSION['mconn'], "select * from `users` where `id`='".intval($drow['from'])."'"));
            $points += $drow['points'];
    ?>
        <tr>
            <td class="DataTD"><?php echo $drow['id']?></td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['date'])?></td>
            <td class="DataTD"><a href="wot.php?id=9&amp;userid=<?php echo intval($drow['from'])?>"><?php echo sanitizeHTML($fromuser['fname'])." ".sanitizeHTML($fromuser['lname'])?></td>
            <td class="DataTD"><a href="account.php?id=43&amp;userid=<?php echo intval($drow['from'])?>"><?php echo sanitizeHTML($fromuser['email'])?></a></td>
            <td class="DataTD"><?php echo intval($drow['points'])?></td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['location'])?></td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['method'])?></td>
            <td class="DataTD"><a href="account.php?id=43&amp;userid=<?php echo intval($drow['to'])?>&amp;assurance=<?php echo intval($drow['id'])?>&amp;csrf=<?php echo make_csrf('admdelassurance')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>" onclick="return confirm('<?php echo sprintf(_("Are you sure you want to revoke the assurance with ID &quot;%s&quot;?"),intval($drow['id']))?>');"><?php echo _("Revoke")?></a></td>
        </tr>
    <?php         }
    ?>
        <tr>
            <td class="DataTD" colspan="4"><b><?php echo _("Total Points")?>:</b></td>
            <td class="DataTD"><?php echo intval($points)?></td>
            <td class="DataTD" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <?php     }

    function showassuredby($ticketno)
    {
    ?>
    <table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
        <tr>
            <td colspan="8" class="title"><?php echo _("Assurance Points The User Issued")?></td>
        </tr>
        <tr>
            <td class="DataTD"><b><?php echo _("ID")?></b></td>
            <td class="DataTD"><b><?php echo _("Date")?></b></td>
            <td class="DataTD"><b><?php echo _("Who")?></b></td>
            <td class="DataTD"><b><?php echo _("Email")?></b></td>
            <td class="DataTD"><b><?php echo _("Points")?></b></td>
            <td class="DataTD"><b><?php echo _("Location")?></b></td>
            <td class="DataTD"><b><?php echo _("Method")?></b></td>
            <td class="DataTD"><b><?php echo _("Revoke")?></b></td>
        </tr>
    <?php         $query = "select * from `notary` where `from`='".intval($_GET['userid'])."' and `deleted` = 0";
        $dres = mysqli_query($_SESSION['mconn'], $query);
        $points = 0;
        while($drow = mysqli_fetch_assoc($dres)) {
            $fromuser = mysqli_fetch_assoc(mysqli_query($_SESSION['mconn'], "select * from `users` where `id`='".intval($drow['to'])."'"));
            $points += intval($drow['points']);
    ?>
        <tr>
            <td class="DataTD"><?php echo intval($drow['id'])?></td>
            <td class="DataTD"><?php echo $drow['date']?></td>
            <td class="DataTD"><a href="wot.php?id=9&userid=<?php echo intval($drow['to'])?>"><?php echo sanitizeHTML($fromuser['fname']." ".$fromuser['lname'])?></td>
            <td class="DataTD"><a href="account.php?id=43&amp;userid=<?php echo intval($drow['to'])?>"><?php echo sanitizeHTML($fromuser['email'])?></a></td>
            <td class="DataTD"><?php echo intval($drow['points'])?></td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['location'])?></td>
            <td class="DataTD"><?php echo sanitizeHTML($drow['method'])?></td>
            <td class="DataTD"><a href="account.php?id=43&userid=<?php echo intval($drow['from'])?>&assurance=<?php echo intval($drow['id'])?>&amp;csrf=<?php echo make_csrf('admdelassurance')?>&amp;ticketno=<?php echo sanitizeHTML($ticketno)?>" onclick="return confirm('<?php echo sprintf(_("Are you sure you want to revoke the assurance with ID &quot;%s&quot;?"),intval($drow['id']))?>');"><?php echo _("Revoke")?></a></td>
        </tr>
    <?php         }
    ?>
        <tr>
            <td class="DataTD" colspan="4"><b><?php echo _("Total Points")?>:</b></td>
            <td class="DataTD"><?php echo intval($points)?></td>
            <td class="DataTD" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <?} ?>
<br/><br/>
<?php } }

if(isset($_GET['shownotary'])) {
    switch($_GET['shownotary']) {
        case 'assuredto':
            showassuredto($ticketno);
            break;
        case 'assuredby':
            showassuredby($ticketno);
            break;
        case 'assuredto15':
            output_received_assurances(intval($_GET['userid']),1,$ticketno);
            break;
        case 'assuredby15':
            output_given_assurances(intval($_GET['userid']),1, $ticketno);
            break;
    }
}
