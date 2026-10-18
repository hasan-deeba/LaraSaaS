import React, { useEffect } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { bootLaraSaaS, t } from '../larasaas';

interface Member {
    user_id: number;
    role: string;
    email: string | null;
    name: string | null;
    joined_at?: string | null;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    token: string;
    expires_at?: string | null;
    accepted_at?: string | null;
    pending: boolean;
}

interface RoleDefinitions {
    [key: string]: { label?: string; implies?: string[] };
}

/**
 * GET /app/workspaces/{workspace} → team management: members with granular
 * workspace-level roles (Owner, Admin, Member), the invitation system and
 * role changes. Only registered when `larasaas.multi_tenant.enabled` is true.
 */
export default function Team() {
    const { workspace, members, invitations, can_manage, is_owner, roles, urls, larasaas } = usePage<{
        workspace: { id: number; name: string; slug: string; role: string | null };
        members: Member[];
        invitations: Invitation[];
        can_manage: boolean;
        is_owner: boolean;
        roles: RoleDefinitions;
        urls: { invite: string; revoke: string; role: string; remove: string; back: string };
        larasaas: any;
    }>().props;

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    const inviteForm = useForm<{ email: string; role: string }>({ email: '', role: 'member' });

    const invite = (event: React.FormEvent) => {
        event.preventDefault();
        inviteForm.post(urls.invite, {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset(),
        });
    };

    const revoke = (invitation: Invitation) => {
        if (confirm(t('teams.revoke_confirm', 'Revoke this invitation?'))) {
            router.delete(urls.revoke.replace('__invitation__', String(invitation.id)), { preserveScroll: true });
        }
    };

    const changeRole = (member: Member, role: string) => {
        router.put(
            urls.role.replace('__user__', String(member.user_id)),
            { role },
            { preserveScroll: true },
        );
    };

    const remove = (member: Member) => {
        if (confirm(t('teams.remove_confirm', 'Remove this member from the workspace?'))) {
            router.delete(urls.remove.replace('__user__', String(member.user_id)), { preserveScroll: true });
        }
    };

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
            <Head title={`${workspace.name} — ${t('teams.title', 'Team')}`} />

            <header className="ls-page__header">
                <h1 className="ls-page__title">{workspace.name}</h1>
                <p className="ls-page__subtitle">{t('teams.subtitle', 'Granular workspace-level roles — Owner, Admin, Member — and invitations.')}</p>
                <a className="ls-card__link" href={urls.back}>
                    ← {t('teams.back', 'All workspaces')}
                </a>
            </header>

            <section className="ls-card">
                <h2>{t('teams.members', 'Members')}</h2>
                <div className="ls-admin-list">
                    {members.map((member) => (
                        <div className="ls-admin-row" key={member.user_id}>
                            <div className="ls-admin-row__main">
                                <strong>{member.name ?? member.email}</strong>
                                <small>{member.email}</small>
                            </div>
                            {is_owner ? (
                                <select className="ls-input ls-input--sm" value={member.role} onChange={(e) => changeRole(member, e.target.value)}>
                                    {Object.entries(roles).map(([key, definition]) => (
                                        <option key={key} value={key}>
                                            {definition.label ?? key}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <span className="ls-flag-pill">{t(`workspaces.roles.${member.role}`, member.role)}</span>
                            )}
                            {can_manage && (
                                <button className="ls-btn ls-btn--sm ls-btn--ghost" type="button" onClick={() => remove(member)}>
                                    {t('teams.remove', 'Remove')}
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            </section>

            {can_manage && (
                <section className="ls-card">
                    <h2>{t('teams.invitations', 'Invitations')}</h2>
                    <form className="ls-form" onSubmit={invite}>
                        <div className="ls-form__grid">
                            <label>
                                {t('teams.invite_email', 'E-mail address')}
                                <input
                                    className="ls-input"
                                    type="email"
                                    required
                                    maxLength={190}
                                    value={inviteForm.data.email}
                                    onChange={(e) => inviteForm.setData('email', e.target.value)}
                                    placeholder="teammate@company.com"
                                />
                            </label>
                            <label>
                                {t('teams.role', 'Role')}
                                <select className="ls-input" value={inviteForm.data.role} onChange={(e) => inviteForm.setData('role', e.target.value)}>
                                    {Object.entries(roles).map(([key, definition]) => (
                                        <option key={key} value={key}>
                                            {definition.label ?? key}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        {Object.keys(inviteForm.errors).length > 0 && (
                            <ul role="alert">
                                {Object.entries(inviteForm.errors).map(([key, error]) => (
                                    <li key={key}>
                                        {key}: {error}
                                    </li>
                                ))}
                            </ul>
                        )}
                        <div className="ls-row">
                            <button className="ls-btn ls-btn--accent" type="submit" disabled={inviteForm.processing}>
                                {t('teams.send_invite', 'Send invitation')}
                            </button>
                        </div>
                    </form>

                    <div className="ls-admin-list">
                        {invitations.length === 0 && <p className="ls-empty">{t('teams.no_invitations', 'No invitations yet.')}</p>}
                        {invitations.map((invitation) => (
                            <div className="ls-admin-row" key={invitation.id}>
                                <div className="ls-admin-row__main">
                                    <strong>{invitation.email}</strong>
                                    <small className="ls-mono">{t(`workspaces.roles.${invitation.role}`, invitation.role)}</small>
                                </div>
                                <span className="ls-flag-pill">
                                    {invitation.accepted_at
                                        ? t('teams.accepted', 'Accepted')
                                        : invitation.pending
                                          ? t('teams.pending', 'Pending')
                                          : t('teams.expired', 'Expired')}
                                </span>
                                {invitation.pending && (
                                    <button className="ls-btn ls-btn--sm ls-btn--ghost" type="button" onClick={() => revoke(invitation)}>
                                        {t('teams.revoke', 'Revoke')}
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}
