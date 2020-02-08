#pragma semicolon 1

#include <sourcemod>
#include <sdktools>

#pragma newdecls required

public Plugin myinfo = {
	name = "Ban Management",
	author = "Kieran",
	description = "Handles xenforo integration for handling bans",
	version = "0.2",
	url = "https://github.com/KieranFYI/Bans"
};

Database hDatabase = null;

public void OnPluginStart() {
	StartSQL();
}

public void OnMapStart()
{
	StartSQL();
}

void StartSQL() {
	if (hDatabase != null)
	{
		delete hDatabase;
		hDatabase = null;
	}
	Database.Connect(OnDatabaseConnect, "forums");
}

public void OnDatabaseConnect(Database db, const char[] error, any data) {

	if (hDatabase != null)
	{
		delete db;
		return;
	}

	hDatabase = db;

	if (hDatabase == null)
	{
		LogError("Failed to connect to database: %s", error);
		return;
	}

	for (int i = 1; i < MAXPLAYERS; i++)
	{
		if (IsClientInGame(i))
		{
			OnClientPostAdminCheck(i);
		}
	}
}

public void OnClientPostAdminCheck(int client) {

	if(IsFakeClient(client)) {
		return;
	}

	if (hDatabase == null) {
		KickClient(client, "Error: Unable to synchronize with database");
		return;
	}
	
	char query[1024];

	char target_id[64];
	if (!GetClientAuthId(client, AuthId_SteamID64, target_id, sizeof(target_id))) {
		KickClient(client, "Error: Unable to identify user");
	}
 	char target_ip[65];
	GetClientIP(client, target_ip, sizeof(target_ip));

	SQL_FormatQuery(hDatabase, query, sizeof(query), "SELECT `ban_reason` FROM `xf_kieran_bans` WHERE `ban_status`=0 AND `type_id`='server' AND (`target_id` IN (SELECT i.identity_value FROM `xf_kieran_identity` i1 JOIN `xf_kieran_identity` i ON i1.user_id=i.user_id AND i.identity_type_id = 'steam' WHERE i1.identity_value = %s AND i1.identity_type_id = 'steam') OR `target_ip` LIKE '%s') AND ( ((UNIX_TIMESTAMP() - timestamp) / 60) < `ban_length` OR `ban_length` = 0 ) ORDER BY ((UNIX_TIMESTAMP() - timestamp) / 60) ASC", target_id, target_ip);
	hDatabase.Query(OnCheckBanQuery, query, GetClientUserId(client));
}

public void OnCheckBanQuery(Database db, DBResultSet rs, const char[] error, any data)
{
	int client;

	if ((client = GetClientOfUserId(data)) == 0) {
		return;
	}

	if (rs.FetchRow()) {
		char ban_reason[255];
		rs.FetchString(0, ban_reason, sizeof(ban_reason));
		KickClient(client,"You are Banned for %s", ban_reason);
	}
}

public Action OnBanClient(int client, int ban_length, int flags, const char[] ban_reason, const char[] kick_message, const char[] command, any admin) {
	char query[2048];

	char server_ip[55];
	int pieces[4];
	char longip = GetConVarInt(FindConVar("hostip"));

	pieces[0] = (longip >> 24) & 0x000000FF;
	pieces[1] = (longip >> 16) & 0x000000FF;
	pieces[2] = (longip >> 8) & 0x000000FF;
	pieces[3] = longip & 0x000000FF;

	Format(server_ip, sizeof(server_ip), "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);

	char admin_id[64];
	char admin_ip[55];
	if (admin == 0) {
		admin_id = "0";
		admin_ip = server_ip;
	} else {
		GetClientAuthId(admin, AuthId_SteamID64, admin_id, sizeof(admin_id));
		GetClientIP(admin, admin_ip, sizeof(admin_ip));
	}

	char target_id[64];
	GetClientAuthId(client, AuthId_SteamID64, target_id, sizeof(target_id));
 	char target_ip[65];
	GetClientIP(client, target_ip, sizeof(target_ip));

	SQL_FormatQuery(hDatabase, query, sizeof(query), "INSERT INTO xf_kieran_bans (server_ip, admin_id, admin_ip, admin_name, target_id, target_ip, target_name, ban_length, ban_reason, type_id, timestamp) VALUES ('%s', '%s', '%s', '%N', '%s', '%s', '%N', %i, '%s', 'server', UNIX_TIMESTAMP())", server_ip, admin_id, admin_ip, admin, target_id, target_ip, client, ban_length, ban_reason);

	hDatabase.Query(ThrowAway, query);

	KickClient(client, "You have been Banned for %s", ban_reason);
	LogMessage("[BANS] User %s has been banned for %d minutes", target_id, ban_length);
	return Plugin_Stop;
}

public void ThrowAway(Database db, DBResultSet results, const char[] error, any data)
{
	if(db == null || results == null)
	{
		LogError("ThrowAway returned error: %s", error);
		return;
	}
}