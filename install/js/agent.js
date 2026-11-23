(function () {
	'use strict';

	var startTs = Date.now();
	var mouseMoves = 0;
	var mouseDistance = 0;
	var lastX = null;
	var lastY = null;
	var keyPresses = 0;
	var clicks = 0;
	var token = null;
	var tokenPromise = null;
	var mouseTimes = [];
	var mousePoints = [];
	var keyTimes = [];
	var startPoint = null;

	document.addEventListener('mousemove', function (e) {
		mouseMoves++;
		if (lastX !== null) {
			var dx = e.pageX - lastX;
			var dy = e.pageY - lastY;
			mouseDistance += Math.sqrt(dx * dx + dy * dy);
		}
		if (!startPoint) {
			startPoint = { x: e.pageX, y: e.pageY };
		}
		lastX = e.pageX;
		lastY = e.pageY;
		mouseTimes.push(Date.now());
		mousePoints.push({ x: e.pageX, y: e.pageY });
		if (mouseTimes.length > 300) {
			mouseTimes.shift();
			mousePoints.shift();
		}
	}, { passive: true });

	document.addEventListener('keydown', function () {
		keyPresses++;
		keyTimes.push(Date.now());
		if (keyTimes.length > 300) {
			keyTimes.shift();
		}
	}, { passive: true });

	document.addEventListener('click', function () {
		clicks++;
	}, { passive: true });

	// Среднее и дисперсия интервалов между событиями; -1 если данных мало
	function intervalStats(times) {
		if (times.length < 4) {
			return { mean: -1, variance: -1 };
		}
		var intervals = [];
		for (var i = 1; i < times.length; i++) {
			intervals.push(times[i] - times[i - 1]);
		}
		var mean = 0;
		for (i = 0; i < intervals.length; i++) {
			mean += intervals[i];
		}
		mean = mean / intervals.length;
		var variance = 0;
		for (i = 0; i < intervals.length; i++) {
			variance += (intervals[i] - mean) * (intervals[i] - mean);
		}
		variance = variance / intervals.length;
		return { mean: Math.round(mean * 10) / 10, variance: Math.round(variance * 10) / 10 };
	}

	// Направленность шагов мыши по окнам из 15 шагов: R — результирующая длина единичных векторов.
	// R -> 1: шаги окна идут строго в одну сторону (робот тянет по прямой к цели).
	// У человека дрожание руки снижает R даже на «прямых» бросках.
	function mouseDirectionality() {
		var windowSize = 15;
		if (mousePoints.length < windowSize + 1) {
			return -1;
		}
		var maxR = 0;
		var straightWindows = 0;
		var totalWindows = 0;
		for (var start = 0; start + windowSize <= mousePoints.length; start++) {
			var sumX = 0;
			var sumY = 0;
			var count = 0;
			var dist = 0;
			for (var i = start + 1; i < start + windowSize; i++) {
				var dx = mousePoints[i].x - mousePoints[i - 1].x;
				var dy = mousePoints[i].y - mousePoints[i - 1].y;
				var len = Math.sqrt(dx * dx + dy * dy);
				if (len > 2) {
					sumX += dx / len;
					sumY += dy / len;
					count++;
					dist += len;
				}
			}
			if (count >= windowSize - 2 && dist > 100) {
				totalWindows++;
				var r = Math.sqrt(sumX * sumX + sumY * sumY) / count;
				if (r > maxR) {
					maxR = r;
				}
				if (r > 0.985) {
					straightWindows++;
				}
			}
		}
		if (maxR === 0 || totalWindows === 0) {
			return { maxR: -1, straightRatio: -1 };
		}
		return {
			maxR: Math.round(Math.min(1, maxR) * 1000) / 1000,
			straightRatio: Math.round((straightWindows / totalWindows) * 1000) / 1000
		};
	}

	function isNative(fn) {
		try {
			return typeof fn === 'function' && /\{\s*\[native code\]\s*\}/.test(Function.prototype.toString.call(fn));
		} catch (e) {
			return false;
		}
	}

	function collectFlags() {
		var ua = navigator.userAgent || '';
		var webdriverPatched = false;

		try {
			var descriptor = Object.getOwnPropertyDescriptor(Navigator.prototype, 'webdriver');
			if (!navigator.webdriver && descriptor && descriptor.get && !isNative(descriptor.get)) {
				webdriverPatched = true;
			}
		} catch (e) {}

		var languages = navigator.languages ? navigator.languages.length : 0;
		var gpuRenderer = getGpuInfo().renderer;

		return {
			webdriver: !!navigator.webdriver
				|| !!document.documentElement.getAttribute('webdriver')
				|| !!document.documentElement.getAttribute('driver'),
			webdriverPatched: webdriverPatched,
			domAutomation: !!(window.domAutomation || window.domAutomationController),
			phantom: !!(window.callPhantom || window._phantom),
			nodejs: typeof window.Buffer === 'function'
				|| (typeof window.process === 'object' && window.process.versions && !!window.process.versions.node),
			headlessUA: /HeadlessChrome|PhantomJS|SlimerJS/i.test(ua),
			noLanguagesPlugins: languages === 0 && navigator.plugins.length === 0,
			swiftshaderGPU: /SwiftShader/i.test(gpuRenderer)
		};
	}

	function getMetrics() {
		var mouseStats = intervalStats(mouseTimes);
		var keyStats = intervalStats(keyTimes);
		var direction = mouseDirectionality();
		return {
			durationMs: Date.now() - startTs,
			mouseMoves: mouseMoves,
			mouseDistance: Math.round(mouseDistance),
			keyPresses: keyPresses,
			clicks: clicks,
			mouseIntMean: mouseStats.mean,
			mouseIntVar: mouseStats.variance,
			mouseStraight: direction.maxR,
			mouseStraightRatio: direction.straightRatio,
			keyIntMean: keyStats.mean,
			keyIntVar: keyStats.variance
		};
	}

	// --- Паспорт устройства ---

	function sha256Hex(text) {
		if (window.crypto && window.crypto.subtle) {
			return window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text))
				.then(function (buf) {
					return Array.prototype.map.call(new Uint8Array(buf), function (b) {
						return ('0' + b.toString(16)).slice(-2);
					}).join('');
				});
		}
		return Promise.resolve('');
	}

	// Canvas-фингерпринт по рецепту nf из статьи про антифрод Т-Банка
	function drawFingerprintCanvas(withAltFont) {
		var canvas = document.createElement('canvas');
		canvas.width = 2000;
		canvas.height = 200;
		var ctx = canvas.getContext('2d');

		ctx.textBaseline = 'alphabetic';
		ctx.fillStyle = '#f60';
		ctx.fillRect(125, 1, 62, 20);

		ctx.fillStyle = '#069';
		ctx.font = (withAltFont ? '18pt' : '11pt') + ' no-real-font-123';
		ctx.fillText('Nullam dictum felis eu pede mollis', 2, 15);

		ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
		ctx.font = '18pt Arial';
		ctx.fillText('Nullam dictum felis eu pede mollis ', 4, 45);

		var arcs = [
			[100, 100, 50, 'rgb(255,0,255)'],
			[200, 100, 40, 'rgb(0,255,255)'],
			[300, 100, 30, 'rgb(255,255,0)'],
			[100, 100, 20, 'rgb(255,0,255)'],
			[200, 100, 10, 'rgb(0,255,255)']
		];
		arcs.forEach(function (a) {
			ctx.beginPath();
			ctx.arc(a[0], a[1], a[2], 0, Math.PI * 2, true);
			ctx.closePath();
			ctx.fillStyle = a[3];
			ctx.fill();
		});

		return canvas.toDataURL();
	}

	var fpPromise = null;
	var fpImage = '';
	function getFingerprint() {
		if (!fpPromise) {
			fpPromise = new Promise(function (resolve) {
				try {
					var first = drawFingerprintCanvas(false);
					var second = drawFingerprintCanvas(true);
					fpImage = first;
					resolve(sha256Hex(first + '_' + second));
				} catch (e) {
					resolve('');
				}
			});
		}
		return fpPromise;
	}

	function getFingerprintImage() {
		return fpImage;
	}

	function getGpuInfo() {
		try {
			var canvas = document.createElement('canvas');
			var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
			if (!gl) {
				return { renderer: '', vendor: '' };
			}
			var ext = gl.getExtension('WEBGL_debug_renderer_info');
			return {
				renderer: ext ? String(gl.getParameter(ext.UNMASKED_RENDERER_WEBGL)) : '',
				vendor: ext ? String(gl.getParameter(ext.UNMASKED_VENDOR_WEBGL)) : ''
			};
		} catch (e) {
			return { renderer: '', vendor: '' };
		}
	}

	function collectDevice() {
		var gpu = getGpuInfo();
		return {
			gpuRenderer: gpu.renderer,
			gpuVendor: gpu.vendor,
			screen: screen.width + 'x' + screen.height + 'x' + (window.devicePixelRatio || 1),
			platform: navigator.platform || '',
			cores: navigator.hardwareConcurrency || 0,
			memory: navigator.deviceMemory || 0,
			touch: ('ontouchstart' in window) || navigator.maxTouchPoints > 0,
			timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
			language: navigator.language || ''
		};
	}

	function issueToken() {
		if (token) {
			return Promise.resolve(token);
		}
		if (!tokenPromise) {
			tokenPromise = getFingerprint()
				.then(function (fp) {
					return fetch('/bitrix/services/main/ajax.php?action=alexw:antibot.api.issue', {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'fp=' + encodeURIComponent(fp || '')
					});
				})
				.then(function (r) { return r.json(); })
				.then(function (r) {
					token = r && r.data ? r.data.token : null;
					return token;
				})
				.catch(function () {
					tokenPromise = null;
					return null;
				});
		}
		return tokenPromise;
	}

	function buildPayload(honeypotValue) {
		return getFingerprint().then(function (fp) {
			return JSON.stringify({
				flags: collectFlags(),
				metrics: getMetrics(),
				hp: honeypotValue || '',
				fp: fp || '',
				device: collectDevice()
			});
		});
	}

	function injectHoneypot(form) {
		var wrap = document.createElement('div');
		wrap.style.cssText = 'position:absolute;left:-5000px;top:auto;width:1px;height:1px;overflow:hidden;';
		wrap.setAttribute('aria-hidden', 'true');
		var input = document.createElement('input');
		input.type = 'text';
		input.name = 'ab_hp';
		input.tabIndex = -1;
		input.autocomplete = 'off';
		wrap.appendChild(input);
		form.appendChild(wrap);
		return input;
	}

	function injectHidden(form, name, value) {
		var existing = form.querySelector('input[name="' + name + '"]');
		if (existing) {
			existing.value = value;
			return;
		}
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = value;
		form.appendChild(input);
	}

	function protect(form) {
		var honeypot = injectHoneypot(form);
		var submitting = false;

		form.addEventListener('submit', function (e) {
			if (submitting) {
				return;
			}
			e.preventDefault();
			issueToken().then(function (issuedToken) {
				return buildPayload(honeypot.value).then(function (payload) {
					submitting = true;
					injectHidden(form, 'ab_token', issuedToken || '');
					injectHidden(form, 'ab_payload', payload);
					form.submit();
				});
			});
		});
	}

	function postTrack(image) {
		return getFingerprint().then(function (fp) {
			if (!fp) {
				return;
			}
			var body = 'fp=' + encodeURIComponent(fp)
				+ '&device=' + encodeURIComponent(JSON.stringify(collectDevice()));
			if (image) {
				body += '&image=' + encodeURIComponent(image);
			}
			return fetch('/bitrix/services/main/ajax.php?action=alexw:antibot.api.track', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			})
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.data && r.data.needImage && fpImage) {
						return postTrack(fpImage);
					}
				});
		});
	}

	function trackOnLoad() {
		try {
			if (window.sessionStorage && sessionStorage.getItem('ab_tracked')) {
				return;
			}
			postTrack('').then(function () {
				if (window.sessionStorage) {
					sessionStorage.setItem('ab_tracked', '1');
				}
			});
		} catch (e) {}
	}

	window.AlexwAntibot = {
		collectFlags: collectFlags,
		getMetrics: getMetrics,
		getFingerprint: getFingerprint,
		getFingerprintImage: getFingerprintImage,
		collectDevice: collectDevice,
		issueToken: issueToken,
		buildPayload: buildPayload,
		protect: protect,
		track: postTrack
	};

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('form[data-antibot]');
		for (var i = 0; i < forms.length; i++) {
			protect(forms[i]);
		}
		trackOnLoad();
	});
})();
