import * as React from "react"

import { cn } from "@/lib/utils"

function RadioGroup({
  className,
  legend,
  children,
  ...props
}: React.ComponentProps<"fieldset"> & {
  legend?: string
}) {
  return (
    <fieldset data-slot="radio-group" className={cn("grid gap-2", className)} {...props}>
      {legend ? (
        <legend className="text-sm font-medium text-foreground">{legend}</legend>
      ) : null}
      {children}
    </fieldset>
  )
}

function Radio({
  className,
  label,
  ...props
}: React.ComponentProps<"input"> & {
  label: string
}) {
  const id = props.id ?? props.value?.toString()

  return (
    <label
      htmlFor={id}
      className="flex cursor-pointer items-center gap-2 text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
    >
      <input
        {...props}
        type="radio"
        id={id}
        data-slot="radio"
        className={cn(
          "accent-primary focus-visible:ring-ring/50 size-4 shrink-0 rounded-full border border-input shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50",
          className
        )}
      />
      <span>{label}</span>
    </label>
  )
}

export { Radio, RadioGroup }
