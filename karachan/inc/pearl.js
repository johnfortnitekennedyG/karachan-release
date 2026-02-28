// https://obfuscator.io/#code
// Use "Self Defending" and "Debug Protection"
(async()=>{
	const results = {
		mobile: 0, //looks totally normal.
		dsc: 0,
		txl: 0,
		vpn: 0,
		cg: 0,
		wm: 0,
		hm: 0,
		wi: 0,
		hi: 0,
		vv: 'karachan',
		kp: ''
	}

	// Screen resolution check (if unpassable sets wm to -1)
	// This is fucking evil.
	try {
		results.wm = screen.width;
		results.hm = screen.height;
		results.wi = window.innerWidth;
		results.hi = window.innerHeight;
	} catch(e) {
		results.wm = -1
	}

	// Mobile check
	try {
		mobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) ? 1 : 0;
	} catch(e) {
		mobile = 0;
	}

	// Kekpass (Yes, this is also evil. You don't know you're pozzed)
	try {
		results.kp = localStorage.getItem("kekpass") || '';
	} catch(e) {
		results.kp = '';
	}

	// Incognito check (chrome only, still sketchy)
	try {
		const fs = window.RequestFileSystem || window.webkitRequestFileSystem
		if(fs){
			fs(window.TEMPORARY, 100, ()=>{}, ()=>{ results.cg = 1 })
		}
	} catch(e){
		results.cg = 1
	}

	function trySocket(url, onSuccess){
		return new Promise((resolve)=>{
			let ws
			try {
				ws = new WebSocket(url)
				ws.onopen = ()=>{
					ws.close()
					onSuccess()
					resolve()
				}
				ws.onerror = ()=>resolve()
			} catch(e){
				resolve()
			}
		})
	}

	// WebSocket checks
	await trySocket("ws://127.0.0.1:6463/?v=1", ()=>{ results.dsc = 1 })
	await trySocket("ws://127.0.0.1:1701/tuxler", ()=>{ results.txl = 1 })
	await trySocket("ws://127.0.0.1:1700/tuxler", ()=>{ results.txl = 1 })

	// WebRTC leak check
	await new Promise((resolve)=>{
		let ips = new Set()

		let pc = new RTCPeerConnection({
			iceServers: [{urls: "stun:stun.l.google.com:19302"}]
		})

		pc.createDataChannel("check")

		pc.onicecandidate = (event)=>{
			if(event.candidate){
				let ipRegex = /([0-9]{1,3}(\.[0-9]{1,3}){3})/g
				let match = event.candidate.candidate.match(ipRegex)
				if(match){
					match.forEach(ip=>ips.add(ip))
				}
			} else {
				pc.close()

				// bruh logic: if we get any private IPs, it's likely local
				let hasLocal = false
				ips.forEach(ip=>{
					if(/^10\./.test(ip) || /^192\.168\./.test(ip) || /^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(ip)){
						hasLocal = true
					}
				})

				results.vpn = hasLocal ? 0 : 1
				resolve()
			}
		}

		pc.createOffer().then(offer=>pc.setLocalDescription(offer))
	})

	// send it
	fetch('/kcindex.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(results)})
})()