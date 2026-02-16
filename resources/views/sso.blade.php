<!DOCTYPE html>
<html>
<head><title>SSO Login - VSISPanel</title></head>
<body>
<p>Logging you in...</p>
<script>
  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');
  const redirect = params.get('redirect') || '/dashboard';
  if (token) {
    localStorage.setItem('vsispanel_token', token);
    window.location.href = redirect;
  } else {
    document.body.innerHTML = '<p>Invalid SSO request. No token provided.</p>';
  }
</script>
</body>
</html>
