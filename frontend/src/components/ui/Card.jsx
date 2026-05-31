import { cn } from '@/lib/utils'

export function Card({ className, children }) {
  return <div className={cn('card', className)}>{children}</div>
}

export function MetricCard({ label, value, icon: Icon, trend, color = 'brand' }) {
  const colors = {
    brand: 'text-brand-600 bg-brand-50 dark:bg-brand-900/20',
    green: 'text-green-600 bg-green-50 dark:bg-green-900/20',
    red: 'text-red-600 bg-red-50 dark:bg-red-900/20',
    amber: 'text-amber-600 bg-amber-50 dark:bg-amber-900/20',
  }
  return (
    <div className="card">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-slate-500 dark:text-slate-400">{label}</p>
          <p className="text-2xl font-semibold mt-1">{value}</p>
          {trend && <p className="text-xs text-slate-400 mt-1">{trend}</p>}
        </div>
        {Icon && (
          <div className={cn('p-2 rounded-lg', colors[color])}>
            <Icon size={20} />
          </div>
        )}
      </div>
    </div>
  )
}
