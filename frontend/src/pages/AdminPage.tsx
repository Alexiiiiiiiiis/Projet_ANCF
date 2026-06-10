import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../services/api'
import type { AdminStats } from '../types/transport'
import { Spinner } from '../components/ui/Spinner'

interface ApiUser {
  id: number
  email: string
  firstName: string
  lastName: string
  roles: string[]
  isActive: boolean
  createdAt: string
}

interface UsersResponse {
  users: ApiUser[]
  total: number
  page: number
  limit: number
}

interface ApiLog {
  id: number
  endpoint: string
  httpMethod: string
  statusCode: number
  responseTimeMs: number
  createdAt: string
}

interface LogsResponse {
  logs: ApiLog[]
}

interface SystemParam {
  id: number
  key: string
  value: string
  label: string
  description: string
  type: string
  updatedAt: string
}

interface ParamsResponse {
  parameters: SystemParam[]
}

const StatCard = ({ label, value, icon }: { label: string; value: string | number; icon: string }) => (
  <div className="rounded-xl bg-white p-5 shadow-sm">
    <div className="flex items-center justify-between">
      <span className="text-2xl">{icon}</span>
      <span className="text-2xl font-bold text-gray-900">{value}</span>
    </div>
    <p className="mt-2 text-sm text-gray-500">{label}</p>
  </div>
)

export function AdminPage() {
  const queryClient = useQueryClient()
  const [editingParam, setEditingParam] = useState<number | null>(null)
  const [editValue, setEditValue] = useState('')

  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['admin-stats'],
    queryFn: async () => {
      const { data } = await api.get<AdminStats>('/admin/stats')
      return data
    },
    refetchInterval: 60_000,
  })

  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: ['admin-users'],
    queryFn: async () => {
      const { data } = await api.get<UsersResponse>('/admin/users?limit=20')
      return data
    },
  })

  const { data: logsData } = useQuery({
    queryKey: ['admin-logs'],
    queryFn: async () => {
      const { data } = await api.get<LogsResponse>('/admin/api-logs')
      return data
    },
    refetchInterval: 30_000,
  })

  const { data: paramsData, isLoading: paramsLoading } = useQuery({
    queryKey: ['admin-params'],
    queryFn: async () => {
      const { data } = await api.get<ParamsResponse>('/admin/parameters')
      return data
    },
  })

  const toggleMutation = useMutation({
    mutationFn: (userId: number) => api.put(`/admin/users/${userId}/toggle`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-users'] }),
  })

  const updateParamMutation = useMutation({
    mutationFn: ({ id, value }: { id: number; value: string }) =>
      api.put(`/admin/parameters/${id}`, { value }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-params'] })
      setEditingParam(null)
    },
  })

  const startEdit = (param: SystemParam) => {
    setEditingParam(param.id)
    setEditValue(param.value)
  }

  const saveEdit = (id: number) => {
    updateParamMutation.mutate({ id, value: editValue })
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Administration</h1>

      {statsLoading ? (
        <div className="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
      ) : stats && (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
          <StatCard label="Utilisateurs" value={stats.totalUsers} icon="👥" />
          <StatCard label="Requêtes aujourd'hui" value={stats.requestsPerDay} icon="📊" />
          <StatCard label="Erreurs aujourd'hui" value={stats.errorsToday} icon="⚠️" />
          <StatCard label="Alertes actives" value={stats.activeAlerts} icon="🚨" />
          <StatCard label="Temps de réponse moy." value={`${stats.avgResponseMs}ms`} icon="⚡" />
          <StatCard label="Uptime" value={`${stats.uptime}%`} icon="✅" />
        </div>
      )}

      <div className="rounded-2xl bg-white p-5 shadow-sm">
        <h2 className="mb-4 font-semibold text-gray-700">Paramètres système</h2>
        {paramsLoading ? (
          <div className="flex justify-center py-6">
            <Spinner />
          </div>
        ) : paramsData?.parameters.length === 0 ? (
          <p className="text-sm text-gray-400">Aucun paramètre configuré.</p>
        ) : (
          <div className="space-y-3">
            {paramsData?.parameters.map((param) => (
              <div key={param.id} className="flex items-center justify-between rounded-lg border border-gray-100 p-3">
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-800">{param.label}</p>
                  <p className="text-xs text-gray-400 truncate">{param.description}</p>
                </div>
                <div className="ml-4 flex items-center gap-2">
                  {editingParam === param.id ? (
                    <>
                      {param.type === 'boolean' ? (
                        <select
                          value={editValue}
                          onChange={(e) => setEditValue(e.target.value)}
                          className="rounded border border-gray-300 px-2 py-1 text-sm"
                        >
                          <option value="true">Oui</option>
                          <option value="false">Non</option>
                        </select>
                      ) : (
                        <input
                          type={param.type === 'number' ? 'number' : 'text'}
                          value={editValue}
                          onChange={(e) => setEditValue(e.target.value)}
                          className="w-24 rounded border border-gray-300 px-2 py-1 text-sm text-right"
                        />
                      )}
                      <button
                        onClick={() => saveEdit(param.id)}
                        disabled={updateParamMutation.isPending}
                        className="rounded bg-blue-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-blue-700"
                      >
                        OK
                      </button>
                      <button
                        onClick={() => setEditingParam(null)}
                        className="rounded bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200"
                      >
                        Annuler
                      </button>
                    </>
                  ) : (
                    <>
                      <span className="rounded bg-gray-100 px-2.5 py-1 text-sm font-mono font-semibold text-gray-700">
                        {param.type === 'boolean' ? (param.value === 'true' ? 'Oui' : 'Non') : param.value}
                      </span>
                      <button
                        onClick={() => startEdit(param)}
                        className="rounded bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-600 hover:bg-blue-100"
                      >
                        Modifier
                      </button>
                    </>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="rounded-2xl bg-white p-5 shadow-sm">
        <h2 className="mb-4 font-semibold text-gray-700">Utilisateurs</h2>
        {usersLoading ? (
          <div className="flex justify-center py-6">
            <Spinner />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-gray-500">
                  <th className="pb-2 pr-4 font-medium">Nom</th>
                  <th className="pb-2 pr-4 font-medium">Email</th>
                  <th className="pb-2 pr-4 font-medium">Rôle</th>
                  <th className="pb-2 pr-4 font-medium">Statut</th>
                  <th className="pb-2 font-medium">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {usersData?.users.map((u) => (
                  <tr key={u.id}>
                    <td className="py-2.5 pr-4 font-medium text-gray-800">{u.firstName} {u.lastName}</td>
                    <td className="py-2.5 pr-4 text-gray-600">{u.email}</td>
                    <td className="py-2.5 pr-4">
                      {u.roles.includes('ROLE_ADMIN') ? (
                        <span className="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">Admin</span>
                      ) : (
                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">User</span>
                      )}
                    </td>
                    <td className="py-2.5 pr-4">
                      {u.isActive ? (
                        <span className="text-green-600">Actif</span>
                      ) : (
                        <span className="text-gray-400">Inactif</span>
                      )}
                    </td>
                    <td className="py-2.5">
                      <button
                        onClick={() => toggleMutation.mutate(u.id)}
                        className={`rounded px-2 py-1 text-xs font-medium ${
                          u.isActive
                            ? 'bg-red-50 text-red-600 hover:bg-red-100'
                            : 'bg-green-50 text-green-700 hover:bg-green-100'
                        }`}
                      >
                        {u.isActive ? 'Désactiver' : 'Activer'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="rounded-2xl bg-white p-5 shadow-sm">
        <h2 className="mb-4 font-semibold text-gray-700">Journaux API récents</h2>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b text-left text-gray-500">
                <th className="pb-2 pr-3 font-medium">Endpoint</th>
                <th className="pb-2 pr-3 font-medium">Méthode</th>
                <th className="pb-2 pr-3 font-medium">Statut</th>
                <th className="pb-2 pr-3 font-medium">Temps</th>
                <th className="pb-2 font-medium">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {logsData?.logs.map((log) => (
                <tr key={log.id}>
                  <td className="py-2 pr-3 font-mono text-gray-700 truncate max-w-[200px]">{log.endpoint}</td>
                  <td className="py-2 pr-3">
                    <span className={`font-semibold ${log.httpMethod === 'GET' ? 'text-blue-600' : log.httpMethod === 'POST' ? 'text-green-600' : 'text-orange-600'}`}>
                      {log.httpMethod}
                    </span>
                  </td>
                  <td className="py-2 pr-3">
                    <span className={`font-bold ${log.statusCode < 300 ? 'text-green-600' : log.statusCode < 500 ? 'text-orange-500' : 'text-red-600'}`}>
                      {log.statusCode}
                    </span>
                  </td>
                  <td className="py-2 pr-3 text-gray-500">{log.responseTimeMs}ms</td>
                  <td className="py-2 text-gray-400">{new Date(log.createdAt).toLocaleTimeString('fr-FR')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
