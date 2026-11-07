@if(session('registered_username'))
<style>
.registration-success-dialog{position:fixed;inset:0;margin:auto;box-sizing:border-box;width:370px;max-width:calc(100vw - 28px);max-height:calc(100dvh - 28px);overflow:auto;padding:0;border:1px solid #cbdced;border-top:3px solid #265990;border-radius:0;background:#fff;color:#173b65;box-shadow:0 20px 64px #0c244b38;font-family:inherit}
.registration-success-dialog::backdrop{background:#102c4cb8;backdrop-filter:blur(3px)}
.registration-success-content{padding:20px}.registration-success-heading{display:flex;flex-direction:column;align-items:center;gap:13px;margin-bottom:14px;text-align:center}.registration-success-icon{display:grid;place-items:center;flex:0 0 48px;width:48px;height:48px;margin-top:5px;border:1px solid #b7dfc8;border-radius:50%;background:linear-gradient(145deg,#f1fcf5,#ddf4e6);color:#238352;box-shadow:0 0 0 6px #edf8f1,0 5px 12px #23835212}.registration-success-icon svg{width:26px;height:26px;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.registration-success-dialog h2{margin:0;color:#204f83;font-size:17px;font-weight:600;line-height:1.4}.registration-success-dialog p{margin:0 0 12px;color:#667e9b;font-size:11px;line-height:1.6}.registration-success-school{display:block;text-align:center;margin-bottom:5px;color:#315c8b;font-size:12px;font-weight:500;line-height:1.6;overflow-wrap:anywhere}
.registration-success-credentials{display:grid;margin:14px 0 12px;border:1px solid #d6e3f3;background:#f5f8fd}.registration-success-credentials>div{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.8fr);align-items:center;gap:12px;padding:11px 12px}.registration-success-credentials>div+div{border-top:1px solid #d6e3f3}.registration-success-credentials dt{margin:0;color:#6c83a0;font-size:9px;font-weight:500;line-height:1.5}.registration-success-credentials dd{margin:0;text-align:right;color:#173f70;font-size:13px;font-weight:600;line-height:1.5;overflow-wrap:anywhere}.registration-success-credentials code{font-family:inherit}
#registration-success-description{text-align:center}.registration-success-dialog form{margin:0}.registration-success-dialog .login-button{width:100%;height:auto;min-height:42px;padding:9px 12px;font-size:12px}.registration-success-dialog .registration-success-note{font-size:10px;margin-bottom:16px;line-height:1.7}
@media(max-width:380px){.registration-success-content{padding:17px 15px}.registration-success-dialog h2{font-size:16px}.registration-success-credentials>div{gap:8px;padding:10px}.registration-success-credentials dd{font-size:12px}}
</style>
<dialog open class="registration-success-dialog" data-registration-success aria-labelledby="registration-success-title" aria-describedby="registration-success-description">
    <div class="registration-success-content">
        <div class="registration-success-heading"><span class="registration-success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/></svg></span>
        <h2 id="registration-success-title">Registration successful</h2></div>
        <strong class="registration-success-school">{{ session('registered_school_name') }}</strong>
        <p id="registration-success-description">Your school is registered. Your sign-in credentials are:</p>
        <dl class="registration-success-credentials">
            <div><dt>Username / Registration number</dt><dd><code>{{ session('registered_username') }}</code></dd></div>
            <div><dt>Password</dt><dd><code>mdes123</code></dd></div>
        </dl>
        <p class="registration-success-note">Keep these details. You can change your password later in Settings.</p>
        <form method="dialog"><button class="login-button" type="submit" autofocus>Continue to sign in</button></form>
    </div>
</dialog>
<script>
const registrationSuccess = document.querySelector('[data-registration-success]');
if (registrationSuccess && typeof registrationSuccess.showModal === 'function') {
    registrationSuccess.removeAttribute('open');
    registrationSuccess.showModal();
    registrationSuccess.addEventListener('close', () => document.getElementById('username')?.focus());
}
</script>
@endif
