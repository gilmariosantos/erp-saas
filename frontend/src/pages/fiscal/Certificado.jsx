import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { ShieldCheck, Upload, AlertTriangle, Check } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '@/api/client'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'

export default function Certificado() {
  const [arquivo, setArquivo] = useState(null)
  const [senha, setSenha] = useState('')
  const [preview, setPreview] = useState(null)

  const validar = useMutation({
    mutationFn: () => {
      const form = new FormData()
      form.append('certificado', arquivo)
      form.append('senha', senha)
      return api.post('/v1/fiscal/certificado/validar', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onSuccess: ({ data }) => {
      setPreview(data)
      if (data.valido) toast.success('Certificado válido!')
      else toast.error(data.erro)
    },
    onError: () => toast.error('Erro ao validar certificado.'),
  })

  return (
    <div className="space-y-6 max-w-2xl">
      <div className="flex items-center gap-2">
        <ShieldCheck className="text-brand-600" size={24} />
        <h1 className="text-2xl font-semibold">Certificado Digital</h1>
      </div>

      <Card>
        <div className="flex items-start gap-2 p-3 mb-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
          <AlertTriangle size={18} className="text-amber-600 mt-0.5 shrink-0" />
          <p className="text-sm text-amber-800 dark:text-amber-300">
            Seu certificado é armazenado de forma criptografada e nunca é compartilhado.
            A senha é protegida e jamais exibida novamente.
          </p>
        </div>

        <div className="space-y-4">
          <div>
            <label className="label">Arquivo do certificado (.pfx ou .p12)</label>
            <input
              type="file"
              accept=".pfx,.p12"
              className="input"
              onChange={(e) => { setArquivo(e.target.files[0]); setPreview(null) }}
            />
          </div>

          <div>
            <label className="label">Senha do certificado</label>
            <input
              type="password"
              className="input"
              value={senha}
              onChange={(e) => setSenha(e.target.value)}
              placeholder="••••••••"
            />
          </div>

          <Button
            onClick={() => validar.mutate()}
            disabled={!arquivo || !senha || validar.isPending}
          >
            <Upload size={18} />
            {validar.isPending ? 'Validando...' : 'Validar certificado'}
          </Button>
        </div>

        {preview?.valido && (
          <div className="mt-6 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg space-y-2">
            <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
              <Check size={18} /> <span className="font-medium">Certificado válido</span>
            </div>
            <div className="text-sm space-y-1 text-slate-600 dark:text-slate-300">
              {preview.razao_social && <p>Titular: {preview.razao_social}</p>}
              {preview.cnpj && <p>CNPJ: {preview.cnpj}</p>}
              <p className="flex items-center gap-2">
                Validade: {preview.validade}
                {preview.dias_para_vencer <= 30 && (
                  <Badge cor="yellow">Vence em {preview.dias_para_vencer} dias</Badge>
                )}
              </p>
            </div>
            <Button className="mt-2" onClick={() => toast.success('Confirme o upload para salvar.')}>
              Salvar certificado
            </Button>
          </div>
        )}

        {preview && !preview.valido && (
          <div className="mt-6 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
            <p className="text-sm text-red-700 dark:text-red-400">{preview.erro}</p>
          </div>
        )}
      </Card>
    </div>
  )
}
