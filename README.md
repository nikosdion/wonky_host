# A wonky host

A Docker Composer implementation of a host which uses an _intentionally misconfigured_ NginX instance as an HTTP proxy.

> ⚠️ **THIS IS NOT A GOOD EXAMPLE OF HOW TO RUN NGINX AS A REVERSE PROXY!** It is **INTENTIONALLY** misconfigured to not set the HTTPS status and hostname correctly on the origin server. 

This makes for a good simulation of a standard site sitting behind a CDN, TLS terminator, or reverse proxy in a way which does not set the correct `Host` or HTTPS status on the origin webserver. This covers two real world use cases we have seen plenty of over the past few years:

1. Sites behind CloudFlare where the very generic origin Apache server is accessed over plain HTTP (**HERESY!**), but the site is being accessed over HTTPS. This tends to cause misidentification of the HTTPS status in Joomla, and related tools and software.
2. Sites behind a TLS terminator or reverse proxy which does not set the HTTP `Host` header. This tends to cause misidentification of the hostname in Joomla, and related tools and software unless an explicit `$live_site` is set.

## Usage

Requires Docker with Docker Composer. The default installation of Docker Desktop covers these requirements. **It has not been tested with Podman** but I see no reason it shouldn't work.

- Copy `.env.example` to `.env` and edit `DOMAIN_NAME` if you want the proxy to respond to a hostname other than the default `wonky.local.web`. Do NOT use localhost!
- Create a new self-signed TLS certificate pair in `certificates` for the hostname you chose above. The public key must be named `cert.pem` and the private key `privkey.pem`.
- Put your site in a **subdirectory** of `web-root`, e.g. `web-root/joomla`.
- Run `docker compose up --build` to bring up the orchestrated containers.
- Access your site as `https://<DOMAIN_NAME>/joomla` where `joomla` is the name of the directory you put your site into.

For the database use the following connection information:
- Hostname: `mysql-container`
- Database: `repro`
- Username: `repro_user`
- Password: `t9YS8vYxhxptmHFd2a`

If you need root access to the database, the password is `uXmquBhjBvnpnWAftm`.

The database files are stored in the `mysql` directory under the repository's root. 

## Implementation notes

Use a [`hosts` file](https://en.wikipedia.org/wiki/Hosts_(file)) on your computer to make sure the subdomain you configure resolves to 127.0.0.1. The hostname the proxy responds to is controlled by the `DOMAIN_NAME` variable in `.env` (see `.env.example`); its default is `repro.local.web`. Pick a hostname _that is not `localhost`_ since you're running this in Docker and things _will_ get weird if you use `localhost`.

The NginX configuration is shipped as `nginx.conf.template`, which the upstream nginx image renders into `/etc/nginx/conf.d/repro.conf` via `envsubst` at container start.

The NginX instance is configured to pass the `X-Forwarded-Host`, `X-Real-IP`, `X-Forwarded-For`, and `X-Forwarded-Proto` headers. This is very similar to what CDNs would be doing in this case.

## Why does this even exist?

I have been working on workarounds for these issues in software I manage – personal and professional projects alike. I needed a reproduction environment for the common mistakes people make.

## How would one configure their server correctly?

If you're using a CDN and commercial hosting, it all comes down to your server and CDN configuration, and a single Joomla configuration option (as of this writing, contemporary to Joomla! 5.3). If you are using a different setup, read the information below on Hostname, IP Address, and Scheme.

All CDNs set the `Host` header. Therefore, there's no provision for this.

When it comes to the IP address, Joomla has a Global Configuration option called “Behind Load Balancer”. You must enable it.

> ⚠️ **WARNING!** If someone accesses your origin web server directly (i.e. they know its IP address), they can effectively spoof their IP address by sending an `X-Forwarded-For` header. You should ask your CDN provider for the IP blocks they are going to use to access your web server, and limit access to your web server to only these IP blocks. This can even be done in `.htaccess` on cheap commercial hosts. 

When it comes to the scheme (HTTP vs HTTPS) you **MUST** have your CDN only ever access your web server over HTTPS. Most CDNs allow you to create TLS certificates for your origin server; you should do that. Moreover, most CDNs allow you to handle the HTTP to HTTPS redirection at the CDN level; you should do that too. This means that your web server will only ever see HTTPS traffic, which makes it simple when it comes to `.htaccess` files, or NginX configuration: you don't need to make any changes compared to accessing your web server directly.

### Hostname

The reverse proxy should be setting the HTTP `Host` header.

### IP Address

If you have Apache. you should enable `mod_remoteip` and add the following to your VirtualHost configuration:
```apacheconf
RemoteIPHeader X-FORWARDED-FOR
RemoteIPTrustedProxy 192.168.37.0/24
RemoteIPTrustedProxy 192.168.42.0/24
```
The `RemoteIPTrustedProxy` lines are optional, but they contribute to security by only allowing the X-Forwarded-For header to be considered from specific proxy origins only.

If you have NginX. You should enable the `ngx_http_realip_module` and add the following to your `server` block:
```nginx configuration
real_ip_header X-Forwarded-For;
set_real_ip_from 192.168.37.0/24;
set_real_ip_from 192.168.42.0/24;
```
The `set_real_ip_from` lines are optional, but they contribute to security by only allowing the X-Forwarded-For header to be considered from specific proxy origins only.

### Scheme (HTTP vs HTTPS)

Ideally, you should have your proxy / TLS terminator / CDN / load balancer only be accessible over HTTPS, and access your site only over HTTPS. If that's not possible, you have very limited options. The server will be setting its environment variables based on the protocol it's being accessed on, not based on the URL the proxy sees. This requires a few workarounds when taking action conditional on HTTP vs HTTPS access.

> ⚠️ Using the X-Forwarded-Proto to decide whether the request is HTTPS is _generally unsafe_ if the web server can be accessed directly over the Internet. The server configuration below should only be used in two cases: a. If the web server can only be accessed through the proxy due to the network topology / OS-level firewall; or b. There is additional server configuration in place to prevent access to the server from anything that is NOT going through a proxy. In any other case it's possible for an attacker to launch a MITM attack which sends the `X-Forwarded-Proto: https` HTTP header to the origin server even though the request is over plain HTTP, thereby gaining access to unencrypted traffic (a form of HTTP downgrade attack). For this reason, it is **CRUCIAL** for security that the proxy only ever accesses the origin over HTTPS, and there's a provision **on the proxy side** to redirect HTTP traffic to HTTPS.

If you have Apache. You can look at both the `HTTPS` environment variable and the `X-Forwarded-Proto` header when doing things like redirecting to HTTPS:
```apacheconf
<IfModule mod_rewrite.c>
  RewriteEngine on
  RewriteCond %{HTTPS} !=on
  RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

If you have NginX. You can look at both the built-in `$scheme` variable and the `X-Forwarded-Proto` header when doing things like redirecting to HTTPS: 
```nginx configuration
    set $nkdHTTPSFlag 0;

    # Check if the X-Forwarded-Proto header something other than "https"
    if ($http_x_forwarded_proto = "https") {
        set $nkdHTTPSFlag 1;
    }

    # Alternatively, check the URL scheme reported by NginX.
    if ($scheme = "https") {
        set $nkdHTTPSFlag 1;
    }
    
    if ($nkdHTTPSFlag != 1) {
        return 301 https://$host$request_uri;
    }
```