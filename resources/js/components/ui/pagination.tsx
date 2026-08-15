import { ChevronLeft, ChevronRight } from "lucide-react"
import * as React from "react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type PaginationProps = {
  page: number
  lastPage: number
  onPageChange?: (page: number) => void
  className?: string
}

function Pagination({
  page,
  lastPage,
  onPageChange,
  className,
}: PaginationProps) {
  const previousDisabled = page <= 1
  const nextDisabled = page >= lastPage

  return (
    <nav
      aria-label="Paginación"
      data-slot="pagination"
      className={cn("flex items-center justify-between gap-3", className)}
    >
      <p className="text-muted-foreground text-sm">
        Página {page} de {lastPage}
      </p>
      <div className="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={previousDisabled}
          onClick={() => onPageChange?.(page - 1)}
          aria-label="Página anterior"
        >
          <ChevronLeft />
          Anterior
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={nextDisabled}
          onClick={() => onPageChange?.(page + 1)}
          aria-label="Página siguiente"
        >
          Siguiente
          <ChevronRight />
        </Button>
      </div>
    </nav>
  )
}

export { Pagination }
