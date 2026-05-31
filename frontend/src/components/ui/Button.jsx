import { cn } from '@/lib/utils'

export function Button({ variant = 'primary', className, children, ...props }) {
  const variants = {
    primary: 'btn-primary',
    secondary: 'btn-secondary',
    danger: 'btn-danger',
  }
  return (
    <button className={cn(variants[variant], className)} {...props}>
      {children}
    </button>
  )
}
