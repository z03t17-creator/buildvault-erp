import PageStub from "@/Components/PageStub";
import { useForm } from "@inertiajs/react";

export default function Create({ tower }) {
  const { data, setData, post, processing, errors } = useForm({ name: "" });
  return (
    <PageStub title="Create floor" links={[{ href: route("towers.floors.index", tower.id), label: "Back" }]}>
      <form onSubmit={(e) => { e.preventDefault(); post(route("towers.floors.store", tower.id)); }} className="space-y-3">
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
