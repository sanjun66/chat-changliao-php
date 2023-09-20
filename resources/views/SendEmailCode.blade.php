<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        #app {
            line-height: 150%;
        }
    </style>
</head>

<body>
    <div id="app">
        <div>亲爱的用户：</div>
        <br />
        <div>您好！</div>
        <br />
        <div>感谢您使用{{env('MAIL_FROM_NAME')}}。您正在进行邮箱验证，请在验证码输入框中输入此次验证码: <span style="color: red">{{$emailData['code']}}</span>（5分钟内有效）以完成验证。</div>
        <div>如非本人操作，请忽略此邮件，由此给您带来的不便请谅解！</div>
        <br />
        <div>{{env('MAIL_FROM_NAME')}}平台</div>
    </div>
</body>

</html>