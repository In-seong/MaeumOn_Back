<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>계정 삭제 - ON:CARE</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6; background: #f9f9f9; }
        .container { max-width: 520px; margin: 0 auto; padding: 48px 20px; min-height: 100vh; }
        .card { background: #fff; border-radius: 16px; padding: 32px 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo-icon { width: 48px; height: 48px; margin: 0 auto 8px; background: #FFF3ED; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .logo-icon svg { width: 28px; height: 28px; }
        .logo-name { font-size: 18px; font-weight: 700; color: #333; }
        .logo-sub { font-size: 13px; color: #999; }
        h1 { font-size: 22px; font-weight: 700; text-align: center; margin-bottom: 8px; color: #111; }
        .desc { font-size: 14px; color: #777; text-align: center; margin-bottom: 28px; }
        .info-box { background: #FFF8F3; border-left: 3px solid #FF7B22; padding: 14px 16px; border-radius: 6px; margin-bottom: 24px; }
        .info-box h3 { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px; }
        .info-box ul { padding-left: 18px; }
        .info-box li { font-size: 13px; color: #555; margin-bottom: 4px; }
        .warn-box { background: #FFF5F5; border: 1px solid #FFE0E0; padding: 14px 16px; border-radius: 6px; margin-bottom: 24px; }
        .warn-box p { font-size: 13px; color: #D32F2F; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group input { width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #FF7B22; box-shadow: 0 0 0 2px rgba(255,123,34,0.1); }
        .btn-delete { width: 100%; height: 48px; background: #D32F2F; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; margin-top: 8px; }
        .btn-delete:hover { background: #B71C1C; }
        .btn-delete:disabled { background: #CCC; cursor: not-allowed; }
        .result { text-align: center; padding: 20px 0; display: none; }
        .result.success { display: block; }
        .result h2 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .result p { font-size: 14px; color: #777; }
        .result .check { width: 56px; height: 56px; border-radius: 50%; background: #4CAF50; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; }
        .result .check svg { width: 28px; height: 28px; }
        .error-msg { font-size: 13px; color: #D32F2F; margin-top: 8px; text-align: center; display: none; }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #BBB; }
        .footer a { color: #FF7B22; text-decoration: none; }
        .retention { margin-top: 24px; }
        .retention h3 { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .retention table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .retention th { background: #F8F8F8; padding: 8px 10px; text-align: left; font-weight: 600; color: #555; border-bottom: 1px solid #E8E8E8; }
        .retention td { padding: 8px 10px; border-bottom: 1px solid #F0F0F0; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 108 108" fill="none">
                        <rect x="44" y="10" width="20" height="69" rx="7" fill="#EE7623"/>
                        <rect x="10" y="44" width="69" height="20" rx="7" fill="#F8B500"/>
                        <rect x="44" y="44" width="20" height="20" fill="#E8501E"/>
                    </svg>
                </div>
                <div class="logo-name">ON:CARE</div>
                <div class="logo-sub">(주)뱅크다이어리</div>
            </div>

            <div id="form-section">
                <h1>계정 삭제 요청</h1>
                <p class="desc">계정과 관련 데이터의 삭제를 요청할 수 있습니다</p>

                <div class="info-box">
                    <h3>삭제 시 취해야 할 단계</h3>
                    <ul>
                        <li>아래 양식에 아이디와 비밀번호를 입력합니다</li>
                        <li>"계정 삭제 요청" 버튼을 클릭합니다</li>
                        <li>본인 확인 후 계정이 즉시 삭제됩니다</li>
                    </ul>
                </div>

                <div class="warn-box">
                    <p><strong>주의:</strong> 계정 삭제 시 모든 개인정보, 고객 데이터, 청구 기록이 영구적으로 삭제되며 복구할 수 없습니다.</p>
                </div>

                <form id="delete-form" onsubmit="return handleDelete(event)">
                    <div class="form-group">
                        <label>아이디</label>
                        <input type="text" id="username" placeholder="아이디를 입력하세요" required />
                    </div>
                    <div class="form-group">
                        <label>비밀번호</label>
                        <input type="password" id="password" placeholder="비밀번호를 입력하세요" required />
                    </div>
                    <p class="error-msg" id="error-msg"></p>
                    <button type="submit" class="btn-delete" id="btn-submit">계정 삭제 요청</button>
                </form>

                <div class="retention">
                    <h3>삭제되거나 보관되는 데이터</h3>
                    <table>
                        <tr><th>데이터 유형</th><th>처리 방법</th></tr>
                        <tr><td>계정 정보 (아이디, 비밀번호)</td><td>즉시 삭제</td></tr>
                        <tr><td>개인정보 (이름, 연락처)</td><td>즉시 삭제</td></tr>
                        <tr><td>고객 관리 데이터</td><td>즉시 삭제</td></tr>
                        <tr><td>보험금 청구 기록</td><td>법정 보관기간(3개월) 후 삭제</td></tr>
                        <tr><td>로그인 기록</td><td>즉시 삭제</td></tr>
                    </table>
                </div>
            </div>

            <div id="result-section" class="result">
                <div class="check">
                    <svg fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2>계정이 삭제되었습니다</h2>
                <p>모든 개인정보가 안전하게 삭제되었습니다.<br>ON:CARE를 이용해 주셔서 감사합니다.</p>
            </div>
        </div>

        <div class="footer">
            <p>&copy; 2026 (주)뱅크다이어리 &middot; <a href="/privacy">개인정보 처리방침</a></p>
            <p style="margin-top: 4px;">문의: support@bohumon.co.kr</p>
        </div>
    </div>

    <script>
        async function handleDelete(e) {
            e.preventDefault();
            var username = document.getElementById('username').value;
            var password = document.getElementById('password').value;
            var errorEl = document.getElementById('error-msg');
            var btn = document.getElementById('btn-submit');

            if (!username || !password) {
                errorEl.textContent = '아이디와 비밀번호를 입력하세요';
                errorEl.style.display = 'block';
                return false;
            }

            btn.disabled = true;
            btn.textContent = '처리 중...';
            errorEl.style.display = 'none';

            try {
                var loginRes = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ username: username, password: password, role: 'AGENT' })
                });

                if (!loginRes.ok) {
                    var loginErr = await loginRes.json();
                    throw new Error(loginErr.message || '아이디 또는 비밀번호가 올바르지 않습니다.');
                }

                var loginData = await loginRes.json();
                var token = loginData.data ? loginData.data.token : loginData.token;

                var deleteRes = await fetch('/api/auth/delete-account', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token
                    }
                });

                if (!deleteRes.ok) {
                    var delErr = await deleteRes.json();
                    throw new Error(delErr.message || '계정 삭제에 실패했습니다.');
                }

                document.getElementById('form-section').style.display = 'none';
                document.getElementById('result-section').classList.add('success');
            } catch (err) {
                errorEl.textContent = err.message;
                errorEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = '계정 삭제 요청';
            }

            return false;
        }
    </script>
</body>
</html>
