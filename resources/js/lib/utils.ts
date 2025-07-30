import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/**
 * Helper function to ensure CSRF cookie is set before making API requests
 */
export async function ensureCSRFToken(): Promise<void> {
  // Always fetch the CSRF cookie to ensure it's fresh
  // This is needed for Sanctum stateful authentication
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
    },
  });
}

/**
 * Helper function to make authenticated API requests
 */
export async function authenticatedFetch(url: string, options: RequestInit = {}): Promise<Response> {
  // Ensure CSRF token is available
  await ensureCSRFToken();
  
  // Get CSRF token from cookies
  const getCookie = (name: string): string | null => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift() || null;
    return null;
  };
  
  // Try to get XSRF token from cookie (Laravel's default)
  const csrfToken = getCookie('XSRF-TOKEN') || 
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  
  // Decode the token if it's from cookie (Laravel encodes it)
  const decodedToken = csrfToken.includes('%3D') ? decodeURIComponent(csrfToken) : csrfToken;
  
  // Merge headers with default auth headers
  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': decodedToken, // Laravel expects X-XSRF-TOKEN for cookie-based CSRF
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...options.headers,
  };
  
  // Make the request with credentials
  return fetch(url, {
    ...options,
    headers,
    credentials: 'include', // Use 'include' to ensure cookies are sent
  });
}
