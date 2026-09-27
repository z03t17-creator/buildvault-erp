import PageStub from "@/Components/PageStub";
import { useForm } from "@inertiajs/react";

export default function Create({ projects, roles }) {
  const { data, setData, post, processing, errors } = useForm({ name: "", role: "laborer", project_id: "", daily_rate_usd: 0, overtime_rate_usd: 0 });
  return (
    <PageStub title="Create worker" links={[{ href: route("workers.index"), label: "Back" }]}>
      <form onSubmit={(e) => { e.preventDefault(); post(route("workers.store")); }} className="space-y-3">
        <div>
          <label className="block text-sm">Name</label>
          <input className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-600 text-xs">{errors.name}</p>}
        </div>
        <div>
          <label className="block text-sm">Role</label>
          <select className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.role} onChange={(e) => setData("role", e.target.value)}>
            {(roles || []).map((r) => <option key={r} value={r}>{r}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-sm">Project</label>
          <select className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.project_id} onChange={(e) => setData("project_id", e.target.value)}>
            <option value="">—</option>
            {(projects || []).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
        <button disabled={processing} className="rounded bg-emerald-600 px-3 py-2 text-white text-sm">Save</button>
      </form>
    </PageStub>
  );
}
