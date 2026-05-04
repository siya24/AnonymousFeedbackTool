<?php declare(strict_types=1); ?>
<?php
/**
 * Email notification template.
 *
 * @var string $badge
 * @var string $badgeColor
 * @var string $title
 * @var string $message
 * @var string $reference
 * @var string $category
 * @var string $caseUrl
 * @var string $ctaLabel
 * @var string $submittedAt
 */
$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$submittedRow = ($submittedAt ?? '') !== ''
    ? '<tr><td style="padding:6px 0;color:#666;font-size:13px;">Submitted</td><td style="padding:6px 0;font-size:13px;">' . $esc((string) $submittedAt) . '</td></tr>'
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                <tr>
                    <td style="background:#9d2722;padding:24px 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold;">Voice Without Fear</p>
                                    <p style="margin:4px 0 0;color:rgba(255,255,255,0.8);font-size:13px;">Legal Aid South Africa &ndash; Anonymous Feedback System</p>
                                </td>
                                <td align="right">
                                    <span style="background:<?= $esc((string) $badgeColor) ?>;color:#fff;font-size:11px;font-weight:bold;padding:4px 10px;border-radius:12px;letter-spacing:0.5px;"><?= $esc((string) $badge) ?></span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h2 style="margin:0 0 8px;font-size:20px;color:#222;"><?= $esc((string) $title) ?></h2>
                        <p style="margin:0 0 24px;color:#555;font-size:14px;line-height:1.6;"><?= $esc((string) $message) ?></p>
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-radius:6px;padding:16px;margin-bottom:24px;">
                            <tr>
                                <td style="padding:6px 0;color:#666;font-size:13px;width:110px;">Reference</td>
                                <td style="padding:6px 0;font-size:13px;font-weight:bold;color:#9d2722;"><?= $esc((string) $reference) ?></td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#666;font-size:13px;">Category</td>
                                <td style="padding:6px 0;font-size:13px;"><?= $esc((string) $category) ?></td>
                            </tr>
                            <?= $submittedRow ?>
                        </table>
                        <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                            <tr>
                                <td style="background:#008AC4;border-radius:6px;">
                                    <a href="<?= $esc((string) $caseUrl) ?>" style="display:inline-block;padding:12px 28px;color:#fff;text-decoration:none;font-size:14px;font-weight:bold;"><?= $esc((string) $ctaLabel) ?> &rarr;</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0;font-size:12px;color:#999;">Or copy this link:<br>
                            <a href="<?= $esc((string) $caseUrl) ?>" style="color:#008AC4;word-break:break-all;"><?= $esc((string) $caseUrl) ?></a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f9fa;padding:16px 32px;border-top:1px solid #e9ecef;">
                        <p style="margin:0;font-size:11px;color:#aaa;text-align:center;">This is an automated notification from the Voice Without Fear system.<br>Legal Aid South Africa &mdash; Confidential</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
