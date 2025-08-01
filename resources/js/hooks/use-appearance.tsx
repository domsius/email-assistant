export type Appearance = "light" | "dark" | "system";

export function initializeTheme() {
  // Always use light theme
  document.documentElement.classList.remove("dark");
}

export function useAppearance() {
  // Always return light theme
  return { 
    appearance: "light" as const, 
    updateAppearance: () => {} 
  } as const;
}
