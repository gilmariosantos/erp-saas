import { cn } from '@/lib/utils'

export function FormField({ label, error, required, children, className }) {
  return (
    <div className={className}>
      {label && (
        <label className="label">
          {label} {required && <span className="text-red-500">*</span>}
        </label>
      )}
      {children}
      {error && <p className="text-xs text-red-500 mt-1">{error}</p>}
    </div>
  )
}

export function Input({ error, className, ...props }) {
  return <input className={cn('input', error && 'border-red-400', className)} {...props} />
}

export function Select({ error, className, children, ...props }) {
  return (
    <select className={cn('input', error && 'border-red-400', className)} {...props}>
      {children}
    </select>
  )
}

export function Textarea({ error, className, ...props }) {
  return <textarea className={cn('input min-h-[80px]', error && 'border-red-400', className)} {...props} />
}
