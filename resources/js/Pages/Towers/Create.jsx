import PageStub from "@/Components/PageStub";
import { useForm } from "@inertiajs/react";

export default function Create({ project }) {
  const { data, setData, post, processing, errors } = useForm({ name: "" });
  return (
    <PageStub title="Create tower" links={[{ href: route("projects.towers.index", project.id), label: "Back" }]}>
      <form onSubmit={(e) => { e.preventDefault(); post(route("projects.towers.store", project.id)); }} className="space-y-3">
        <div>
          <label className="block text-sm">Name</label>
          <input className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-600 text-xs">{errors.name}</p>}
        </div>
        <button disabled={processing} className="rounded bg-emerald-600 px-3 py-2 text-white text-sm">Save</button>
      </form>
    </PageStub>
  );
}
