import { Toaster as Sonner, ToasterProps } from "sonner"
import type { CSSProperties } from "react"

const Toaster = ({ ...props }: ToasterProps) => {
  const resolvedTheme: ToasterProps["theme"] =
    (typeof window !== "undefined"
      ? (localStorage.getItem("appearance") as ToasterProps["theme"] | null)
      : null) ?? "system"

  return (
    <Sonner
      theme={resolvedTheme}
      className="toaster group"
      position="bottom-right"
      richColors
      closeButton
      expand
      toastOptions={{
        classNames: {
          toast: "shadow-sm",
        },
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
        } as CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }
