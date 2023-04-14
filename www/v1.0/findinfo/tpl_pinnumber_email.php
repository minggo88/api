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

                회원님의 계정을 핀번호 재설정하라는 요청을 받았습니다.<br />
                걱정하지 마세요. 회원님의 계정은 안전합니다.<br />
                비밀전호 재설정 요청을 하시지 않으신 경우, 이 메일을 무시해 주십시오.<br /><br />
                회원님이 요청하신 경우 아래 지침을 따르십시오.<br />
                다음 링크를 클릭하여 새 핀번호를 설정하십시오.<br /><br />
                <a href="<?php echo $domain ?>/repinnumber.html?t=<?php echo $tmp_pw ?>" target="_blank"><b><?php echo $domain ?>/repinnumber.html?t=<?php echo $tmp_pw ?></b></a><br /><br />
                링크를 클릭해도 작동하지 않으면 링크를 브라우저 창에 복사하거나 브라우저 창에 직접 입력할 수 있습니다.<br />
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