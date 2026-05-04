// Password regex: 8+ chars, uppercase, lowercase, digit, NO emojis or non-ASCII
const PASSWORD_REGEX = new RegExp('^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])[A-Za-z0-9!@#$%^&*()\\-_=+\\[\\]{};:\'",.<>?\/\\\\|`~]{8,}$');

// ─── API base: derived from current URL so it works at any deploy path ────────
const API_BASE = (function () {
  const path = window.location.pathname;
  const idx = path.indexOf('/frontend/');
  if (idx !== -1) return path.slice(0, idx); // e.g. /bomra_system
  // login.html / register.html sit directly inside /frontend/
  return path.replace(/\/frontend\/[^/]*$/, '');
})();

function validatePassword(password) {
  if (!PASSWORD_REGEX.test(password)) {
    return "Password must be at least 8 characters with uppercase, lowercase, and a number. Emojis and non-standard characters are not allowed.";
  }
  return null;
}

// ─── Login ────────────────────────────────────────────────────────────────────
async function login() {
  const email    = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;

  if (!email || !password) {
    alert("Please enter your email and password.");
    return;
  }

  try {
    const res = await fetch(API_BASE + "/api/auth/login.php", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    const data = await res.json();

    if (data.status === "success") {
      const { user_id, name, role, force_change } = data.data;
      sessionStorage.setItem("user", JSON.stringify({ user_id, name, role, force_change }));

      if (force_change) {
        // Store the typed temp code so change_password.html can re-validate with the API
        sessionStorage.setItem("temp_code", password);
        window.location.href = "change_password.html";
        return;
      }

      const redirects = {
        supplier:  "dashboard/supplier.html",
        facility:  "dashboard/facility.html",
        inspector: "dashboard/inspector.html",
        admin:     "dashboard/admin.html",
      };

      const dest = redirects[role];
      if (dest) {
        window.location.href = dest;
      } else {
        alert("Unknown role. Please contact support.");
      }
    } else {
      alert(data.message || "Login failed. Please check your credentials.");
    }
  } catch (err) {
    alert("Network error: " + err.message + ". Please ensure the server is running.");
  }
}

// ─── Register ─────────────────────────────────────────────────────────────────
async function register() {
  const name     = document.getElementById("name").value.trim();
  const email    = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;
  const role     = document.getElementById("role").value;

  const allowed = ["supplier", "facility", "inspector"];
  if (!allowed.includes(role)) {
    alert("Invalid role selected.");
    return;
  }

  if (!name || !email || !password) {
    alert("All fields are required.");
    return;
  }

  const pwError = validatePassword(password);
  if (pwError) {
    alert(pwError);
    return;
  }

  try {
    const res = await fetch(API_BASE + "/api/auth/register.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password, role }),
    });

    const data = await res.json();

    if (data.status === "success") {
      alert("Account created successfully. Please log in.");
      window.location.href = "login.html";
    } else {
      alert(data.message || "Registration failed.");
    }
  } catch (err) {
    alert("Network error: " + err.message + ". Please ensure the server is running.");
  }
}

// ─── Logout ───────────────────────────────────────────────────────────────────
async function logout() {
  await fetch(API_BASE + "/api/auth/logout.php", {
    method: "POST",
    credentials: "include",
  });
  sessionStorage.removeItem("user");
  window.location.href = "../login.html";
}

// ─── Guard: redirect to login if not authenticated ───────────────────────────
function guardPage(requiredRole) {
  const stored = sessionStorage.getItem("user");
  if (!stored) {
    window.location.href = "../login.html";
    return null;
  }
  const user = JSON.parse(stored);
  if (requiredRole && user.role !== requiredRole) {
    window.location.href = "../login.html";
    return null;
  }
  return user;
}

// ─── POST helper ─────────────────────────────────────────────────────────────
async function apiPost(path, body) {
  const res = await fetch(API_BASE + "/" + path, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return res.json();
}

// ─── GET helper ──────────────────────────────────────────────────────────────
async function apiGet(path) {
  const res = await fetch(API_BASE + "/" + path, {
    credentials: "include",
  });
  return res.json();
}
