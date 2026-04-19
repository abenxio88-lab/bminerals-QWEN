NGINX and IIS security headers examples

NGINX (add inside `server` block):

```
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Permissions-Policy "interest-cohort=()" always;

# Content Security Policy (start with report-only for testing)
add_header Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; object-src 'none'; base-uri 'self';" always;
```

IIS (web.config snippet):

```xml
<system.webServer>
  <httpProtocol>
    <customHeaders>
      <add name="Strict-Transport-Security" value="max-age=63072000; includeSubDomains; preload" />
      <add name="X-Frame-Options" value="SAMEORIGIN" />
      <add name="X-Content-Type-Options" value="nosniff" />
      <add name="Referrer-Policy" value="strict-origin-when-cross-origin" />
      <add name="X-XSS-Protection" value="1; mode=block" />
      <add name="Permissions-Policy" value="interest-cohort=()" />
      <add name="Content-Security-Policy-Report-Only" value="default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; object-src 'none'; base-uri 'self';" />
    </customHeaders>
  </httpProtocol>
</system.webServer>
```

Notes:
- Test CSP in `report-only` mode first to capture violations without blocking resources.
- Adjust `script-src` and `style-src` to include any CDNs you use (e.g., analytics, tag manager).
