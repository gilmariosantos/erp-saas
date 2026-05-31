export function Spinner({ size = 24 }) {
  return (
    <div className="flex items-center justify-center p-4">
      <div
        className="animate-spin rounded-full border-2 border-slate-200 border-t-brand-600"
        style={{ width: size, height: size }}
      />
    </div>
  )
}
