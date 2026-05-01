/**
 * SiteGround's bot-detection occasionally intercepts REST requests and
 * returns HTTP 202 + `content-type: text/html` + `sg-captcha: challenge`
 * with an HTML meta-refresh body pointing at /.well-known/sgcaptcha/.
 * Our JSON clients throw "invalid JSON" on that body. Detect the signal,
 * reload the page so the browser follows the challenge redirect and
 * re-acquires the clearance cookie, guarded by sessionStorage so we
 * never loop more than once per cooldown window.
 */

const RELOAD_KEY = 'wchs_sg_captcha_reload_ts';
const COOLDOWN_MS = 30_000;

export function isCaptchaChallenge(res: Response): boolean {
	if (res.headers.get('sg-captcha') === 'challenge') return true;
	const ct = res.headers.get('content-type') || '';
	return ct.includes('text/html');
}

export function handleCaptchaChallenge(): boolean {
	if (typeof window === 'undefined') return false;
	let prev = 0;
	try { prev = Number(sessionStorage.getItem(RELOAD_KEY) || '0'); } catch { /* storage blocked */ }
	const now = Date.now();
	if (now - prev < COOLDOWN_MS) return false;
	try { sessionStorage.setItem(RELOAD_KEY, String(now)); } catch { /* storage blocked */ }
	window.location.reload();
	return true;
}
