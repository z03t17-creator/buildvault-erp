import PageStub from "@/Components/PageStub";
import { router, useForm } from "@inertiajs/react";

export default function Matrix({ date, projectId, projects, grid }) {
  const checkIn = useForm({ date, check_in: "08:00", worker_ids: [], floor_id: "" });
  const checkOut = useForm({ date, check_out: "17:00", worker_ids: [] });

  const toggle = (form, id) => {
    const ids = form.data.worker_ids.includes(id)
      ? form.data.worker_ids.filter((x) => x !== id)
      : [...form.data.worker_ids, id];
    form.setData("worker_ids", ids);
  };

  return (
    <PageStub title="Attendance matrix" links={[{ href: route("workers.index"), label: "Workers" }]}>
      <div className="mb-4 flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-sm">Date</label>
          <input type="date" className="mt-1 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" defaultValue={date}
            onChange={(e) => router.get(route("attendance.index"), { date: e.target.value, project_id: projectId || undefined }, { preserveState: true })} />
        </div>
        <div>
          <label className="block text-sm">Project</label>
          <select className="mt-1 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-950" defaultValue={projectId || ""}
            onChange={(e) => router.get(route("attendance.index"), { date, project_id: e.target.value || undefined }, { preserveState: true })}>
            <option value="">All</option>
            {(projects || []).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
      </div>

      <table className="w-full text-sm">
        <thead>
          <tr className="text-start border-b border-slate-200 dark:border-slate-700">
            <th className="py-2 pe-2">Select</th>
            <th className="py-2 pe-2">Worker</th>
            <th className="py-2 pe-2">In</th>
            <th className="py-2 pe-2">Out</th>
            <th className="py-2 pe-2">Status</th>
            <th className="py-2 pe-2">Late</th>
            <th className="py-2">OT</th>
          </tr>
        </thead>
        <tbody>
          {(grid || []).map(({ worker, attendance }) => (
            <tr key={worker.id} className="border-b border-slate-100 dark:border-slate-800">
              <td className="py-2 pe-2">
                <input type="checkbox" checked={checkIn.data.worker_ids.includes(worker.id)}
                  onChange={() => { toggle(checkIn, worker.id); toggle(checkOut, worker.id); }} />
              </td>
              <td className="py-2 pe-2">{worker.name}</td>
              <td className="py-2 pe-2">{attendance?.check_in || "—"}</td>
              <td className="py-2 pe-2">{attendance?.check_out || "—"}</td>
              <td className="py-2 pe-2">{attendance?.status || "—"}</td>
              <td className="py-2 pe-2">{attendance?.late_minutes ?? "—"}</td>
              <td className="py-2">{attendance?.overtime_hours ?? "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="mt-4 flex flex-wrap gap-3">
        <button type="button" className="rounded bg-emerald-600 px-3 py-2 text-white text-sm"
          onClick={() => checkIn.post(route("attendance.check-in"))}>Bulk check-in</button>
        <button type="button" className="rounded bg-slate-700 px-3 py-2 text-white text-sm"
          onClick={() => checkOut.post(route("attendance.check-out"))}>Bulk check-out</button>
      </div>
    </PageStub>
  );
}
