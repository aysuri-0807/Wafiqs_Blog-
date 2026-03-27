document.addEventListener("DOMContentLoaded", () => {
	const loginTab = document.querySelector("#login-tab");
	const signupTab = document.querySelector("#signup-tab");
	/** @type {HTMLFormElement | null} */
	const loginForm = document.querySelector("#login-form");
	/** @type {HTMLFormElement | null} */
	const signupForm = document.querySelector("#signup-form");
	const statusNode = document.querySelector("#auth-status");

	if (!loginTab || !signupTab || !loginForm || !signupForm || !statusNode) {
		return;
	}

	const setStatus = (message, kind = "info") => {
		statusNode.textContent = message;
		statusNode.classList.remove("status-error", "status-success");
		if (kind === "error") statusNode.classList.add("status-error");
		if (kind === "success") statusNode.classList.add("status-success");
	};

	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") {
			return null;
		}
		return new URL(path, window.location.href).toString();
	};

	const showLogin = () => {
		loginForm.classList.remove("d-none");
		signupForm.classList.add("d-none");
		loginTab.classList.remove("btn-outline-info");
		loginTab.classList.add("btn-info", "active");
		signupTab.classList.remove("btn-info", "active");
		signupTab.classList.add("btn-outline-info");
		setStatus("Log in with your account.");
	};

	const showSignup = () => {
		signupForm.classList.remove("d-none");
		loginForm.classList.add("d-none");
		signupTab.classList.remove("btn-outline-info");
		signupTab.classList.add("btn-info", "active");
		loginTab.classList.remove("btn-info", "active");
		loginTab.classList.add("btn-outline-info");
		setStatus("Create a new account.");
	};

	const postJson = async (url, payload) => {
		const response = await fetch(url, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
			},
			credentials: "same-origin",
			body: JSON.stringify(payload),
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) {
			throw new Error(data.error || "Request failed");
		}
		return data;
	};

	loginTab.addEventListener("click", showLogin);
	signupTab.addEventListener("click", showSignup);

	loginForm.addEventListener("submit", async (event) => {
		event.preventDefault();
		const formData = new FormData(loginForm);
		const identifier = String(formData.get("identifier") || "").trim();
		const password = String(formData.get("password") || "");
		const loginApiUrl = resolveApiUrl("api/auth/login.php");
		if (!loginApiUrl) {
			setStatus("Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.", "error");
			return;
		}
		setStatus("Logging in...");

		try {
			const data = await postJson(loginApiUrl, { identifier, password });
			setStatus(`Welcome back, ${data.user?.username || identifier}! Redirecting...`, "success");
			setTimeout(() => {
				window.location.href = "index.html";
			}, 700);
		} catch (error) {
			setStatus(error.message || "Login failed", "error");
		}
	});

	signupForm.addEventListener("submit", async (event) => {
		event.preventDefault();
		const formData = new FormData(signupForm);
		const username = String(formData.get("username") || "").trim();
		const email = String(formData.get("email") || "").trim();
		const password = String(formData.get("password") || "");
		const show_email = Boolean(formData.get("show_email"));
		const signupApiUrl = resolveApiUrl("api/auth/signup.php");
		if (!signupApiUrl) {
			setStatus("Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.", "error");
			return;
		}

		setStatus("Creating your account...");
		try {
			const data = await postJson(signupApiUrl, {
				username,
				email,
				password,
				show_email,
			});
			setStatus(`Account created for ${data.user?.username || username}. Redirecting...`, "success");
			setTimeout(() => {
				window.location.href = "index.html";
			}, 700);
		} catch (error) {
			setStatus(error.message || "Signup failed", "error");
		}
	});

	showLogin();
});
