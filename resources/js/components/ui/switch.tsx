import * as React from "react"

import { cn } from "@/lib/utils"

function Switch({
  className,
  checked,
  defaultChecked,
  onCheckedChange,
  disabled,
  id,
  name,
  value = "on",
  ...props
}: Omit<React.ComponentProps<"button">, "onChange"> & {
  checked?: boolean
  defaultChecked?: boolean
  onCheckedChange?: (checked: boolean) => void
  name?: string
  value?: string
}) {
  const [uncontrolled, setUncontrolled] = React.useState(defaultChecked ?? false)
  const isChecked = checked ?? uncontrolled

  function toggle() {
    const next = !isChecked

    if (checked === undefined) {
      setUncontrolled(next)
    }

    onCheckedChange?.(next)
  }

  return (
    <button
      type="button"
      role="switch"
      id={id}
      aria-checked={isChecked}
      disabled={disabled}
      data-slot="switch"
      data-state={isChecked ? "checked" : "unchecked"}
      className={cn(
        "focus-visible:border-ring focus-visible:ring-ring/50 peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border border-transparent bg-input shadow-xs transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary",
        className
      )}
      onClick={toggle}
      {...props}
    >
      {name ? (
        <input type="hidden" name={name} value={isChecked ? value : ""} />
      ) : null}
      <span
        aria-hidden="true"
        className={cn(
          "pointer-events-none block size-4 rounded-full bg-background shadow-sm ring-0 transition-transform",
          isChecked ? "translate-x-4" : "translate-x-0.5"
        )}
      />
    </button>
  )
}

export { Switch }
