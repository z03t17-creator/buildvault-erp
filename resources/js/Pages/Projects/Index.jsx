import PageStub from "@/Components/PageStub";
import { Link } from "@inertiajs/react";

export default function Index({ projects }) {
  return (
    <PageStub title="Projects" links={[{ href: route("projects.create"), label: "Create project" }]}>
      <ul className="space-y-2">
        {(projects || []).map((p) => (
          <li key={p.id}>
            <Link href={route("projects.show", p.id)} className="text-emerald-700 underline dark:text-emerald-400">
              {p.name}
            </Link>
            <span className="ms-2 text-slate-500">towers: {p.towers_count ?? 0}</span>
          </li>
        ))}
      </ul>
      {!projects?.length && <p>No projects yet.</p>}
    </PageStub>
  );
}
