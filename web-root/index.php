<?php
class MyLib
{
	public function isHttps(): bool
	{
		$scheme      = null;

		// Forwarded HTTP header
		$forwarded = $this->parseForwardedHeader();

		if (is_array($forwarded) && !empty($forwarded['proto'] ?? null) && strtolower($forwarded['proto']) == 'https')
		{
			$scheme = 'https';
		}

		// X-Forwarded-Proto HTTP header
		$xForwardedProto = $this->getHttpHeader('X-Forwarded-Proto');

		if (is_string($xForwardedProto) && strtolower($xForwardedProto) == 'https')
		{
			$scheme = 'https';
		}

		// X-Url-Scheme HTTP header
		$xUrlScheme = $this->getHttpHeader('X-Url-Scheme');

		if (is_string($xUrlScheme) && strtolower($xUrlScheme) == 'https')
		{
			$scheme = 'https';
		}

		// Front-End-Https HTTP header
		$frontEndHttps = $this->getHttpHeader('Front-End-Https');

		if (is_string($frontEndHttps) && strtolower($frontEndHttps) === 'on')
		{
			$scheme = 'https';
		}

		// X-Forwarded-Ssl HTTP header
		$xForwardedSsl = $this->getHttpHeader('X-Forwarded-Ssl');

		if (is_string($xForwardedSsl) && strtolower($xForwardedSsl) === 'on')
		{
			$scheme = 'https';
		}

		if ($scheme === 'https')
		{
			return true;
		}

		return strtolower(($_SERVER['HTTPS'] ?? null) ?: 'off') !== 'off';
	}

	public function getHostname(): string
	{
		$hostname    = null;

		// Forwarded
		$forwarded = $this->parseForwardedHeader();

		if (is_array($forwarded) && !empty($forwarded['host'] ?? null))
		{
			$hostname = trim($forwarded['host'] ?: '');
		}

		// X-Forwarded-Host
		$xForwardedHost = $this->getHttpHeader('X-Forwarded-Host');

		if (is_string($xForwardedHost))
		{
			$hostname = trim($xForwardedHost);
		}

		return ($hostname ?: $_SERVER['HTTP_HOST']) ?? 'akeeba.invalid';
	}


	/**
	 * Get the contents of an HTTP header.
	 *
	 * @param   string  $headerName  The name of the header, e.g. `X-Forwarded-Proto`.
	 *
	 * @return  string|null  The header value; NULL if it's empty, or not set.
	 * @since   10.0.4
	 */
	private function getHttpHeader(string $headerName): ?string
	{
		$headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
		$content   = $_SERVER[$headerKey] ?? null;

		return is_string($content) ? strtolower(trim($content)) : null;
	}

	private function parseForwardedHeader(): ?array
	{
		// Get the header contents
		$content     = $_SERVER['HTTP_FORWARDED'] ?? null;
		$content     = is_string($content) ? strtolower(trim($content)) : null;

		// If the Forwarded header doesn't exist, is empty, or contains garbage we will return NULL.
		if (empty($content))
		{
			return null;
		}

		// Initialise the return array.
		$ret = [];

		/**
		 * Get the key-value pairs.
		 *
		 * The `$content` looks like this:
		 * by=192.168.42.42;for=10.0.0.1;host=example.com;proto=https
		 * */
		$pairs = explode(';', $content);

		foreach ($pairs as $pair)
		{
			// The pair must have an equals sign.
			if (strpos($pair, '=') === false)
			{
				continue;
			}

			[$key, $value] = explode('=', $pair, 2);

			$key   = strtolower(trim($key));
			$value = trim($value);

			// Only accept certain keys
			if (!in_array($key, ['by', 'for', 'host', 'proto']))
			{
				continue;
			}

			$ret[$key] = $value;
		}

		return $ret;
	}

}

$myLib   = new MyLib();
$isHttps = $myLib->isHttps();
$hostname = $myLib->getHostname();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Fun with HTTP headers</title>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
	<script>
        (() => {
            const applyThemeBasedOnPreferences = () => {
                const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-bs-theme', prefersDarkScheme ? 'dark' : 'light');
            };

            // Apply theme on load
            applyThemeBasedOnPreferences();

            // Listen for changes in color scheme preferences
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyThemeBasedOnPreferences);
        })();
	</script>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom border-secondary" data-bs-theme="dark">
	<div class="container-fluid">
		<a class="navbar-brand" href="#">Fun with HTTP headers</a>
	</div>
</nav>
<div class="container mt-5">
	<h2>Parsed headers</h2>

	<div class="container">
		<div class="row mt-3">
			<div class="col h4 border-bottom border-info text-info-emphasis">
				Detection with optional headers
			</div>
		</div>
		<div class="row">
			<div class="col col-12 col-sm-6 col-md-4 col-lg-2 fw-bold">
				Scheme
			</div>
			<div class="col col-12 col-sm-6 col-md-8 col-lg-10">
				<?php if ($isHttps): ?>
					<span class="badge bg-success">HTTPS</span>
				<?php else: ?>
					<span class="badge bg-danger">HTTP</span>
				<?php endif; ?>
			</div>
		</div>
		<div class="row">
			<div class="col col-12 col-sm-6 col-md-4 col-lg-2 fw-bold">
				Hostname
			</div>
			<div class="col col-12 col-sm-6 col-md-8 col-lg-10">
				<code><?= $hostname ?></code>
			</div>
		</div>

		<div class="row mt-3">
			<div class="col h4 border-bottom border-info text-info-emphasis">
				Standard headers
			</div>
		</div>
		<div class="row">
			<div class="col col-12 col-sm-6 col-md-4 col-lg-2 fw-bold">
				Scheme
			</div>
			<div class="col col-12 col-sm-6 col-md-8 col-lg-10">
				<?php if (strtolower($_SERVER['HTTPS'] ?? '') === 'on'): ?>
					<span class="badge bg-success">HTTPS</span>
				<?php else: ?>
					<span class="badge bg-danger">HTTP</span>
				<?php endif; ?>
			</div>
		</div>
		<div class="row">
			<div class="col col-12 col-sm-6 col-md-4 col-lg-2 fw-bold">
				Hostname
			</div>
			<div class="col col-12 col-sm-6 col-md-8 col-lg-10">
				<code><?= $_SERVER['HTTP_HOST'] ?? '&mdash;' ?></code>
			</div>
		</div>
	</div>

	<hr class="my-5">

	<h2>Server Environment</h2>
	<table class="table table-striped">
		<thead>
		<tr>
			<th>Variable</th>
			<th>Content</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($_SERVER as $key => $value): ?>
			<tr>
				<td><?= $key ?></td>
				<td>
					<?php if (is_array($value)): ?>
						<pre><?= print_r($value, true) ?></pre>
					<?php else: ?>
						<?= $value ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
