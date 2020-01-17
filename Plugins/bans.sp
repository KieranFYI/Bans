#include <sourcemod>
#include <sdktools>

public Plugin:myinfo = {
	name = "Ban Management",
	author = "Kieran",
	description = "Handles xenforo integration for handling bans",
	version = "0.2",
	url = "https://github.com/KieranFYI/Bans"
};

new Handle:hDatabase = INVALID_HANDLE;

public OnPluginStart() {
	StartSQL();
}

StartSQL() {
	SQL_TConnect(GotDatabase, "forums");
}
 
public GotDatabase(Handle:owner, Handle:hndl, const String:error[], any:data) {
	if (hndl == INVALID_HANDLE) {
		LogError("[BANS] Database Connection Error: %s", error);
	} else {
		hDatabase = hndl;

		for (new i = 1; i < MAXPLAYERS;i++)
		{
			if (IsClientInGame(i))
			{
				OnClientPostAdminCheck(i);
			}
		}
	}
}

public T_AuthCheck(Handle:owner, Handle:hndl, const String:error[], any:data) {
	new client;

	if ((client = GetClientOfUserId(data)) == 0) {
		return;
	}

	if (hndl == INVALID_HANDLE) {
		LogError("[BANS] Query failed! %s", error);
		PrintToServer("[BANS] Query failed! %s", error);
		KickClient(client, "Error: Unable to synchronize with database");
		return;
	}

	if (SQL_FetchRow(hndl)) {
		decl String:ban_reason[255];
		SQL_FetchString(hndl, 0, ban_reason, sizeof(ban_reason));
		KickClient(client,"You are Banned for %s", ban_reason);
	}
}

public T_MYBan(Handle:owner, Handle:hndl, const String:error[], any:data) {
	if (hndl == INVALID_HANDLE) {
		LogError("[BANS] Query failed! %s", error);
	}
	return;
}

public OnClientPostAdminCheck(client) {

	if(IsFakeClient(client) || hDatabase == INVALID_HANDLE) {
		return;
	}
	
	decl String:query[1024];

	decl String:target_id[64];
	GetClientAuthId(client, AuthId_SteamID64, target_id, sizeof(target_id));
 	decl String:target_ip[65];
	GetClientIP(client, target_ip, sizeof(target_ip));

	SQL_FormatQuery(hDatabase, query, sizeof(query), "SELECT `ban_reason` FROM `xf_kieran_bans` WHERE `ban_status`=0 AND `type_id`='server' AND (`target_id` = %i OR `target_ip` LIKE '%s') AND ( ((UNIX_TIMESTAMP() - timestamp) / 60) < `ban_length` OR `ban_length` = 0 ) ORDER BY ((UNIX_TIMESTAMP() - timestamp) / 60) ASC", target_id, target_ip);
	SQL_TQuery(hDatabase, T_AuthCheck, query, GetClientUserId(client));
}                                                

public Action:OnBanClient(client, ban_length, flags, const String:ban_reason[], const String:kick_message[], const String:command[], any:admin) {
	decl String:query[2048];

	new String:server_ip[55];
	new pieces[4];
	new longip = GetConVarInt(FindConVar("hostip"));

	pieces[0] = (longip >> 24) & 0x000000FF;
	pieces[1] = (longip >> 16) & 0x000000FF;
	pieces[2] = (longip >> 8) & 0x000000FF;
	pieces[3] = longip & 0x000000FF;

	Format(server_ip, sizeof(server_ip), "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);

	decl String:admin_id[64];
	decl String:admin_ip[55];
	if (admin == 0) {
		admin_id = "0";
		admin_ip = server_ip;
	} else {
		GetClientAuthId(admin, AuthId_SteamID64, admin_id, sizeof(admin_id));
		GetClientIP(admin, admin_ip, sizeof(admin_ip));
	}

	decl String:target_id[64];
	GetClientAuthId(client, AuthId_SteamID64, target_id, sizeof(target_id));
 	decl String:target_ip[65];
	GetClientIP(client, target_ip, sizeof(target_ip));

	SQL_FormatQuery(hDatabase, query, sizeof(query), "INSERT INTO xf_kieran_bans (server_ip, admin_id, admin_ip, admin_name, target_id, target_ip, target_name, ban_length, ban_reason, type_id, timestamp) VALUES ('%s', '%s', '%s', '%N', '%s', '%s', '%N', %i, '%s', 'server', UNIX_TIMESTAMP())", server_ip, admin_id, admin_ip, admin, target_id, target_ip, client, ban_length, ban_reason);

	PrintToServer(query);
	SQL_TQuery(hDatabase, T_MYBan, query);
	
	KickClient(client, "You have been Banned for %s", ban_reason);
	LogMessage("[BANS] User %s has been banned for %d minutes", target_id, ban_length);
	return Plugin_Stop;
}
