<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset your password</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  /* Web fonts load in clients that support them (Apple Mail, Gmail web,
     Outlook.com); everywhere else falls back to the Georgia/sans-serif
     stacks set inline below, which is why every element also carries an
     inline font-family rather than relying on this stylesheet alone. */
  @import url('https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Poppins:wght@400;500;600&family=Space+Mono:wght@700&display=swap');
  body { margin: 0; padding: 0; background-color: #0f1318; }
  table { border-collapse: collapse; }
  a { text-decoration: none; }
</style>
</head>
<body style="margin:0; padding:0; background-color:#0f1318;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f1318;">
    <tr>
      <td align="center" style="padding: 40px 16px;">

        <table role="presentation" width="100%" style="max-width:480px;" cellpadding="0" cellspacing="0">

          <!-- Eyebrow -->
          <tr>
            <td align="center" style="padding-bottom: 22px;">
              <span style="font-family: 'Space Mono', 'Courier New', monospace; font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #d1b27f;">
                Tawi Properties
              </span>
            </td>
          </tr>

          <!-- Card -->
          <tr>
            <td style="background-color:#1e2834; border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 40px 36px;">

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding-bottom: 20px;">
                    <span style="font-family: 'Fraunces', Georgia, serif; font-size: 24px; font-weight: 600; color: #f5efe9;">
                      Reset your password
                    </span>
                  </td>
                </tr>

                <tr>
                  <td style="font-family: 'Poppins', Arial, sans-serif; font-size: 14px; line-height: 22px; color: #d8cfc3; padding-bottom: 8px;">
                    Hi {{ $user->name }},
                  </td>
                </tr>
                <tr>
                  <td style="font-family: 'Poppins', Arial, sans-serif; font-size: 14px; line-height: 22px; color: #d8cfc3; padding-bottom: 28px;">
                    We received a request to reset your Tawi Properties account password. Use the code below to continue — it expires in 30 minutes.
                  </td>
                </tr>

                <!-- Code -->
                <tr>
                  <td align="center" style="padding-bottom: 28px;">
                    <table role="presentation" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="background-color: rgba(209,178,127,0.08); border: 1px solid rgba(209,178,127,0.35); border-radius: 10px; padding: 16px 32px;">
                          <span style="font-family: 'Space Mono', 'Courier New', monospace; font-size: 30px; font-weight: 700; letter-spacing: 8px; color: #d1b27f;">
                            {{ $code }}
                          </span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>

                <tr>
                  <td style="font-family: 'Poppins', Arial, sans-serif; font-size: 13px; line-height: 20px; color: #a9a099; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px;">
                    If you didn't request this, you can safely ignore this email — your password will stay exactly as it is.
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding-top: 24px;">
              <span style="font-family: 'Poppins', Arial, sans-serif; font-size: 12px; color: #6b6459;">
                &copy; {{ date('Y') }} Tawi Properties. All rights reserved.
              </span>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</body>
</html>