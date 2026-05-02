// Password regex: 8+ chars, uppercase, lowercase, digit, NO emojis or non-ASCII
const PASSWORD_REGEX = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d!@#$%^&*()\-_=+\[\]{};:'",.<>?/\\|`~]{8,}$/;

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
    const res = await fetch("http://localhost/bomra_system/api/auth/login.php", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    const data = await res.json();

    if (data.status === "success") {
      const { user_id, name, role } = data.data;
      sessionStorage.setItem("user", JSON.stringify({ user_id, name, role }));

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
    alert("Network error. Please try again.");
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
    const res = await fetch("http://localhost/bomra_system/api/auth/register.php", {
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
    alert("Network error. Please try again.");
  }
}

// ─── Logout ───────────────────────────────────────────────────────────────────
async function logout() {
  await fetch("http://localhost/bomra_system/api/auth/logout.php", {
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

// ─── Generic fetch helper (JSON POST to API) ─────────────────────────────────
// Resolves relative to frontend/<role>/ subfolder — "../api/..." goes up to frontend/
async function apiPost(url, body) {
  const res = await fetch("../../" + url, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return res.json();
}
