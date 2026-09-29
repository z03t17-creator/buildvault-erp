import ProjectForm from '@/Pages/Projects/ProjectForm';

export default function Create({ statuses }) {
    return <ProjectForm mode="create" statuses={statuses} />;
}
