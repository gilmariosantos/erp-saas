import { useEffect } from 'react'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

export function Modal({ open, onClose, title, children, size = 'md' }) {
  useEffect(() => {
    const handleEsc = (e) => e.key === 'Escape' && onClose()
    if (open) document.addEventListener('keydown', handleEsc)
    return () => document.removeEventListener('keydown', handleEsc)
  }, [open, onClose])

  if (!open) return null

  const sizes = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className={cn('relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-h-[90vh] overflow-hidden flex flex-col', sizes[size])}>
        <div className="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 className="text-lg font-semibold">{title}</h2>
          <button onClick={onClose} className="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
            <X size={20} />
          </button>
        </div>
        <div className="p-5 overflow-y-auto">{children}</div>
      </div>
    </div>
  )
}
