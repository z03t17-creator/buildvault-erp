import PageStub from "@/Components/PageStub";
import { useForm } from "@inertiajs/react";

export default function Edit({ tower, project }) {
  const { data, setData, put, processing, errors } = useForm({ name: tower.name });
  return (
    <PageStub title={`Edit ${tower.name}`} links={[{ href: route("towers.show", tower.id), label: "Back" }]}>
      <form onSubmit={(e) => { e.preventDefault(); put(route("towers.update", tower.id)); }} className="space-y-3">
        <div>
          <label className="block text-sm">Name</label>
          <input className="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-600 text-xs">{errors.name}</p>}
        </div>
        <button disabled={processing} className="rounded bg-emerald-600 px-3 py-2 text-white text-sm">Update</button>
      </form>
    </PageStub>
  );
}
