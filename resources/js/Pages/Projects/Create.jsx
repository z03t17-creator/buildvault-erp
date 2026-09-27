import PageStub from "@/Components/PageStub";
import { useForm } from "@inertiajs/react";

export default function Create({ statuses }) {
  const { data, setData, post, processing, errors } = useForm({ name: "", status: "planning" });
  return (
    <PageStub title="Create project" links={[{ href: route("projects.index"), label: "Back" }]}>
      <form onSubmit={(e) => { e.preventDefault(); post(route("projects.store")); }} className="space-y-3">
        <div>
          <label className="block text-sm">Name</label>
          <input className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-600 text-xs">{errors.name}</p>}
        </div>
        <div>
          <label className="block text-sm">Status</label>
          <select className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.status} onChange={(e) => setData("status", e.target.value)}>
            {(statuses || []).map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <button disabled={processing} className="rounded bg-emerald-600 px-3 py-2 text-white text-sm">Save</button>
      </form>
    </PageStub>
  );
}
