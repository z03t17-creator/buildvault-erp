import PageStub from "@/Components/PageStub";
import { useForm } from "@inertiajs/react";

export default function Edit({ worker, projects, roles }) {
  const { data, setData, put, processing, errors } = useForm({
    name: worker.name,
    role: worker.role,
    project_id: worker.project_id || "",
    daily_rate_usd: worker.daily_rate_usd,
    overtime_rate_usd: worker.overtime_rate_usd,
  });
  return (
    <PageStub title={`Edit ${worker.name}`} links={[{ href: route("workers.show", worker.id), label: "Back" }]}>
      <form onSubmit={(e) => { e.preventDefault(); put(route("workers.update", worker.id)); }} className="space-y-3">
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
        <button disabled={processing} className="rounded bg-emerald-600 px-3 py-2 text-white text-sm">Update</button>
      </form>
    </PageStub>
  );
}
