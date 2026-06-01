# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.4.x | ✅ |
| < 0.4 | ❌ |

## Reporting a Vulnerability

**Do NOT open a public issue for security vulnerabilities.**

Email: halaspiotr@gmail.com

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

Response time: 48 hours for initial acknowledgment.

## Security Considerations

- This library communicates with TigerBeetle via native library (tb_client)
- Network communication is unencrypted by default (TigerBeetle recommendation)
- Use TLS termination at network layer for production deployments
- Never log or expose TigerBeetle cluster credentials
