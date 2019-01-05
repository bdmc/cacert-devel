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
*/ ?>
<?php 	$userid=0; if(array_key_exists('userid',$_GET)) $userid=intval($_GET['userid']);
	if($userid <= 0)
	{
		$domainsearch = $domain = mysqli_real_escape_string($_SESSION['mconn'], stripslashes($_POST['domain']));
		if(!strstr($domain, "%"))
			$domainsearch = "%$domain%";
		if(preg_match("/^\d+$/",$domain))
			$domainsearch = "";
		$query = "select `users`.`id` as `id`, `domains`.`domain` as `domain`, `domains`.`id`as `domid` from `users`,`domains`
				where `users`.`id`=`domains`.`memid` and
				(`domains`.`domain` like '$domainsearch' or `domains`.`id`='$domain') and
				`domains`.`deleted`=0 and `users`.`deleted`=0 and
				`users`.`verified`=1
				group by `users`.`id` limit 100";
		$res = mysqli_query($_SESSION['mconn'], $query);
		if(mysqli_num_rows($res) >= 1) { ?>
<table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
  <tr>
    <td colspan="5" class="title"><?php echo _("Select Specific User Account Details")?></td>
  </tr>
<?php 	while($row = mysqli_fetch_assoc($res))
	{ ?>
  <tr>
    <td class="DataTD"><?php echo _("Domain")?>:</td>
    <td class="DataTD"><?php echo $row['domid']?></td>
    <td class="DataTD"><a href="account.php?id=43&amp;userid=<?php echo $row['id']?>"><?php echo sanitizeHTML($row['domain'])?></a></td>
  </tr>
<?php } if(mysqli_num_rows($res) >= 100) { ?>
  <tr>
    <td class="DataTD" colspan="3"><?php echo _("Only the first 100 rows are displayed.")?></td>
  </tr>
<?php } else { ?>
  <tr>
    <td class="DataTD" colspan="3"><?php printf(_("%s rows displayed."), mysqli_num_rows($res)); ?></td>
  </tr>
<?php } ?>
</table><br><br>
<?		} elseif(mysqli_num_rows($res) == 1) {
			$row = mysqli_fetch_assoc($res);
			$_GET['userid'] = intval($row['id']);
		} else {
			?><table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
				<tr>
				<td colspan="5" class="title"><?printf(_("No personal domains found matching %s"), sanitizeHTML($domain));?></td>
			</tr>
		</table><br><br><?php 		}

		$query = "select `orgid`,`domain`,`id` from `orgdomains` where `domain` like '$domainsearch' or `id`='$domain' limit 100";
		$res = mysqli_query($_SESSION['mconn'], $query);
		if(mysqli_num_rows($res) >= 1) { ?>
<table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
  <tr>
    <td colspan="5" class="title"><?php echo _("Select Specific Organisation Account Details")?></td>
  </tr>
<?php 	while($row = mysqli_fetch_assoc($res))
	{ ?>
  <tr>
    <td class="DataTD"><?php echo _("Domain")?>:</td>
    <td class="DataTD"><?php echo $row['id']?></td>
    <td class="DataTD"><a href="account.php?id=26&amp;orgid=<?php echo intval($row['orgid'])?>"><?php echo sanitizeHTML($row['domain'])?></a></td>
  </tr>
<?php } if(mysqli_num_rows($res) >= 100) { ?>
  <tr>
    <td class="DataTD" colspan="3"><?php echo _("Only the first 100 rows are displayed.")?></td>
  </tr>
<?php } else { ?>
  <tr>
    <td class="DataTD" colspan="3"><?php printf(_("%s rows displayed."), mysqli_num_rows($res)); ?></td>
  </tr>
<?php } ?>
</table><br><br>
<?		} elseif(mysqli_num_rows($res) == 1) {
			$row = mysqli_fetch_assoc($res);
			$_GET['userid'] = intval($row['id']);
		} else {
			?><table align="center" valign="middle" border="0" cellspacing="0" cellpadding="0" class="wrapper">
				<tr>
				<td colspan="5" class="title"><?printf(_("No organisational domains found matching %s"), sanitizeHTML($domain));?></td>
			</tr>
		</table><br><br><?php 		}
	}

	if($userid > 0)
	{
		header("location: account.php?id=43&userid=".intval($_GET['userid']));
		exit;
	}
?>

