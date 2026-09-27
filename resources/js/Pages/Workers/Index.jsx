import PageStub from "@/Components/PageStub";
import { Link } from "@inertiajs/react";

export default function Index({ workers }) {
  return (
    <PageStub title="Workers" links={[{ href: route("workers.create"), label: "Add worker" }, { href: route("attendance.index"), label: "Attendance" }]}>
      <ul className="space-y-2">
        {(workers || []).map((w) => (
          <li key={w.id}>
            <Link href={route("workers.show", w.id)} className="underline text-emerald-700 dark:text-emerald-400">{w.name}</Link>
            <span className="ms-2 text-slate-500">{w.role}{w.project ? ` · ${w.project.name}` : ""}</span>
          </li>
        ))}
      </ul>
    </PageStub>
  );
}
