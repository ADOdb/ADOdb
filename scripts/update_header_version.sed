s/^([^Vv@]*)(@?version )?([Vv]?[0-9]\.[0-9]+(dev|[a-z]|\.[0-9])?\s+[0-9?]+.*[0-9]+)\s+(\(c\)\s*([0-9]+)-[0-9]+.*Lim.*)$/\1@version   \3\n\1@copyright \5\n\1@copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community/
s/^(.*@copyright.*-201)4/\13/
