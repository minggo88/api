<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ko">
<title></title>
<meta content="text/html; charset=utf-8" http-equiv="content-Type" />
<meta content="text/css" http-equiv="Content-Style-Type" />
<meta content="text/j-vascript" http-equiv="Content-Script-Type" />
<meta content="no-cache" http-equiv="Cache-Control" />
<meta content="no-cache" http-equiv="Pragma" />
<meta content="no" http-equiv="Imagetoolbar" />
<meta content="IE=EmulateIE7" http-equiv="X-UA-Compatible" />
<meta name="MSSmartTagsPreventParsing" content="TRUE" />

<body style="margin:0px; padding:0px">
	<table style="width:690px;" align="center">
		<tr>
			<td style="width:100%;background:#00528C;">
				<table style="width:100%;padding:5px 20px;margin:0px;">
					<tr>
						<td style="width:50%;"><a href="<?php echo $domain ?>" target="_new" style="text-decoration:none;color:#fff;">
								<!-- <img src="//www.nft-n.com/@resource/img/logo/d_hd_logo.webp" alt="<?php echo $config_basic->shop_ename ?>"> -->
								<h1><?php echo $config_basic->shop_ename ?></h1>
							</a></td>
						<td style="width:50%; font-size:15px; font-weight:bold; color:#ffffff;font-family:AppleGothic,sans-serif; text-align:right;padding:0px 10px 0px 0px;"></td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td style="padding:5px 20px; font-family:AppleGothic,sans-serif;">
				<div>
                    <?php $member_info->dear_name_str ?><br /><br />

					<?php _e("We were asked to reset your account.") ?><br />
					<?php _e("Don't worry, your account is safe.") ?><br />
					<?php _e("Ignore the E-Mail if the request to reset your password does not come from you.") ?><br /><br />
					<?php _e("Follow the instructions below if this request comes from you.") ?><br />
					<?php _e("Click the following link to set a new password.") ?><br /><br />
					<a href="<?php echo $domain ?>/repw.html?t=<?php echo $tmp_pw ?>" target="_blank"><b><?php echo $domain ?>/repw.html?t=<?php echo $tmp_pw ?></b></a><br /><br />
					<?php _e("If clicking the link doesn't work you can copy the link into your browser window or type it there directly.") ?><br />
				</div>
				<div style="width:auto; margin:20px 0;">
					<a href="<?php echo $domain ?>" target="_blank"><?php _e("Go Website") ?></a>
				</div>
			</td>
		</tr>
		<tr>
			<td width="100%" valign="middle" style="border-top:1px solid #000; border-bottom:1px solid #000; background:#cdcdcd;text-align:left;color:#595959; font-size:11px; font-family:AppleGothic,sans-serif; padding:5px 20px;">
				<?php echo $config_basic->shop_address ?><br />
				Copyright ⓒ <?php echo $config_basic->shop_ename ?> 2021 All rights reserved.
			</td>
		</tr>
	</table>
</body>

</html>