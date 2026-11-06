    <link rel="stylesheet" href="{{ asset('fonts/poppins.css') }}">
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; min-height:100dvh; display:grid; place-items:center; padding:28px 20px; background:#fff; color:#213d60; font-family:"Poppins",Arial,sans-serif; -webkit-font-smoothing:antialiased; }
        .login-panel { width:100%; max-width:330px; }
        .login-logo { display:block; width:100px; height:100px; object-fit:contain; margin:0 auto 20px; }
        .login-welcome { margin:0; text-align:center; color:#213f67; font-size:22px; font-weight:600; line-height:1.35; letter-spacing:-.4px; }
        .login-subtitle { margin:10px 0 30px; color:#8190a5; text-align:center; font-size:13px; line-height:1.5; }
        .login-form { display:flex; flex-direction:column; gap:19px; }
        .field label { display:block; font-size:12px; font-weight:600; color:#526b89; margin-bottom:8px; }
        .field input { display:block; width:100%; height:44px; border:1px solid #d5dfec; background:#fff; color:#203b5e; padding:10px 12px; border-radius:0; font:inherit; font-size:14px; transition:border-color .15s; }
        .field input::placeholder { color:#a0adbf; font-size:12px; }
        .field input:focus { border-color:#4e7db8; outline:2px solid #e6effb; outline-offset:1px; }
        .field input[aria-invalid=true] { border-color:#c77b7b; }
        .forgot-link { align-self:flex-end; margin-top:-7px; font-size:12px; color:#426fa7; text-decoration:none; }
        .forgot-link:hover { text-decoration:underline; }
        .login-button { height:43px; padding:10px 16px; background:#265990; border:1px solid #265990; color:white; font:inherit; font-size:13px; font-weight:600; border-radius:0; cursor:pointer; transition:background .15s; }
        .login-button:hover { background:#1b4779; }
        a:focus-visible,button:focus-visible { outline:2px solid #5c8cca; outline-offset:3px; }
        .login-message { font-size:12px; line-height:1.6; padding:11px 12px; margin:0 0 18px; background:#f0f5fb; border:1px solid #dbe6f4; color:#476992; }
        .login-error { background:#fff5f5; border-color:#eed4d4; color:#a34c4c; }
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .password-field { position:relative; }
        .password-field input { padding-right:44px; }
        .password-eye { position:absolute; right:4px; top:4px; display:grid; place-items:center; width:36px; height:36px; border:0; background:transparent; color:#7089a6; cursor:pointer; }
        .password-eye:hover { color:#285990; background:#f4f7fc; }
        .password-eye svg { width:18px; height:18px; }
        .password-eye .eye-slash { display:none; }
        .password-eye[aria-pressed=true] .eye-slash { display:block; }
    </style>