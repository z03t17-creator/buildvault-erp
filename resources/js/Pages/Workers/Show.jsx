import PageStub from "@/Components/PageStub";

export default function Show({ worker }) {
  return (
    <PageStub title={worker.name} links={[
      { href: route("workers.index"), label: "Workers" },
      { href: route("workers.edit", worker.id), label: "Edit" },
    ]}>
      <p>Role: {worker.role}</p>
      <p>Daily: {worker.daily_rate_usd} · OT: {worker.overtime_rate_usd}</p>
      <p>Project: {worker.project?.name || "—"}</p>
    </PageStub>
  );
}
